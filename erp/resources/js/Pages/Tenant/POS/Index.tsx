import { Head, router } from '@inertiajs/react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { useTenantRoute } from '@/Hooks/useTenantRoute';
import { useState, useEffect, useRef, useMemo, useCallback } from 'react';
import { useVirtualizer } from '@tanstack/react-virtual';
import { usePosSounds } from '@/Hooks/usePosSounds';
import { toast } from 'sonner';
import Lottie from 'lottie-react';
import loadingAnimation from '@/assets/WaitingLoadingCheck.json';
import errorAnimation from '@/assets/error.json';
import Drawer from '@/components/ui/Drawer';
import { Dialog, DialogContent, DialogHeader, DialogTitle, DialogFooter } from '@/components/ui/dialog';
import { ScrollArea } from '@/components/ui/scroll-area';
import { BlurImage } from '@/components/ui/BlurImage';
import {
    Search,
    ShoppingCart,
    Trash2,
    Plus,
    Minus,
    CreditCard,
    Banknote,
    ArrowLeftRight,
    X,
    Check,
    RotateCcw,
    Grid3X3,
    Home,
    Printer,
    ChevronLeft,
    ChevronRight,
    History
} from 'lucide-react';
import { CashIcon } from '@/components/Icons';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';

// Axios is globally configured by Laravel with CSRF token
declare global {
    interface Window {
        axios: any;
    }
}

interface Product {
    id: number;
    name: string;
    sku: string | null;
    barcode: string | null;
    price: number;
    cost: number;
    stock: number;
    image: string | null;
    category_id: number | null;
    category_name: string | null;
}

interface Category {
    id: number;
    name: string;
}

interface CartItem {
    product: Product;
    quantity: number;
}

interface StoreSettings {
    company_name: string;
    company_rut: string;
    company_address: string;
    company_phone: string;
    company_email: string;
    logo: string;
}

interface Props {
    products: Product[];
    categories: Category[];
    nextReceiptNumber: number;
    storeSettings: StoreSettings;
    todaysSales?: SaleRecord[];
}

interface TicketData {
    receipt_number: number;
    total: number;
    change: number;
    payment_method: string;
    payments?: PaymentSplit[]; // For split payments
    date: string;
    items: Array<{
        product_id: number;
        name: string;
        quantity: number;
        unit_price: number;
        subtotal: number;
        image?: string | null;
    }>;
}

interface PaymentSplit {
    method: 'cash' | 'debit' | 'credit' | 'transfer';
    amount: number;
}

interface SaleRecord {
    receipt_number: string;
    total: number;
    time: string;
    payment_method: string;
    payment_label: string;
    change: number;
    items: TicketData['items'];
    payments?: PaymentSplit[];
    returned_quantities?: Record<number, number>; // Product ID -> Returned Qty
    is_fully_returned?: boolean;
}

export default function Index({ products: initialProducts, categories, nextReceiptNumber, storeSettings, todaysSales = [] }: Props) {
    const tRoute = useTenantRoute();
    const [products, setProducts] = useState<Product[]>(initialProducts);
    const [cart, setCart] = useState<CartItem[]>([]);
    // --- SOUNDS ---
    const { playScan, playSale, playError, playClick } = usePosSounds();

    // --- SEARCH & FILTER ---
    const [searchQuery, setSearchQuery] = useState('');
    const [selectedCategory, setSelectedCategory] = useState<number | null>(null);

    const handleCategorySelect = useCallback((id: number | null) => {
        playClick();
        setSelectedCategory(id);
    }, [playClick]);

    // ... inside rendering categories
    /*
       Note: The actual category rendering logic was not fully visible in grep results,
       but I will use find/replace to target specific onClick handlers if I can't find a centralized handler.
       However, I can safely modify the Payment Method selectors found around line 1260.
    */

    const handlePaymentMethodChange = (method: 'cash' | 'debit' | 'credit' | 'transfer') => {
        if (paymentMethod !== method) {
             playClick();
             setPaymentMethod(method);
        }
    };
    const [paymentMethod, setPaymentMethod] = useState<'cash' | 'debit' | 'credit' | 'transfer'>('cash');
    const [showPaymentModal, setShowPaymentModal] = useState(false);
    const [paidAmount, setPaidAmount] = useState('');
    const [processing, setProcessing] = useState(false);
    const [stockError, setStockError] = useState<number | null>(null);
    const [saleResult, setSaleResult] = useState<{success: boolean; receipt_number: number; total: number; change: number} | null>(null);
    const [saleError, setSaleError] = useState<string | null>(null);
    const [ticketData, setTicketData] = useState<TicketData | null>(null);
    const [showTicketDrawer, setShowTicketDrawer] = useState(false);
    const [showHistoryDrawer, setShowHistoryDrawer] = useState(false);
    const searchInputRef = useRef<HTMLInputElement>(null);
    const ticketRef = useRef<HTMLDivElement>(null);
    const gridContainerRef = useRef<HTMLDivElement>(null);
    const [columnCount, setColumnCount] = useState(7);

    // Split payment state
    const [paymentSplits, setPaymentSplits] = useState<PaymentSplit[]>([]);
    const [splitAmount, setSplitAmount] = useState('');
    const [isSplitPayment, setIsSplitPayment] = useState(false);

    // Session sales history
    const [sessionSales, setSessionSales] = useState<SaleRecord[]>(todaysSales);

    // Search suggestions state
    const SEARCH_HISTORY_KEY = 'pos-search-history';
    const [showSuggestions, setShowSuggestions] = useState(false);
    const [searchHistory, setSearchHistory] = useState<string[]>([]);
    const [selectedSuggestionIndex, setSelectedSuggestionIndex] = useState(-1);

    // Favorites / Frequency tracking
    const FAVORITES_KEY = 'pos-product-frequency';
    const [productFrequency, setProductFrequency] = useState<Record<number, number>>({});

    // Sales Return State
    const [showReturnModal, setShowReturnModal] = useState(false);
    const [returnTicketId, setReturnTicketId] = useState('');
    const [returnTicketData, setReturnTicketData] = useState<SaleRecord | null>(null);
    const [returnItems, setReturnItems] = useState<Record<string, number>>({}); // productId -> qty to return

    // Responsive column count based on container width
    useEffect(() => {
        const container = gridContainerRef.current;
        if (!container) return;

        const updateColumns = () => {
            const width = container.clientWidth;
            // Match Tailwind breakpoints: 2cols base, 3 sm, 4 md, 5 lg, 6 xl, 7 2xl
            if (width >= 1536) setColumnCount(7);      // 2xl
            else if (width >= 1280) setColumnCount(6); // xl
            else if (width >= 1024) setColumnCount(5); // lg
            else if (width >= 768) setColumnCount(4);  // md
            else if (width >= 640) setColumnCount(3);  // sm
            else setColumnCount(2);
        };

        updateColumns();
        const resizeObserver = new ResizeObserver(updateColumns);
        resizeObserver.observe(container);
        return () => resizeObserver.disconnect();
    }, []);

    // Focus search on mount
    useEffect(() => {
        searchInputRef.current?.focus();
    }, []);

    // Load search history from localStorage
    useEffect(() => {
        try {
            const stored = localStorage.getItem(SEARCH_HISTORY_KEY);
            if (stored) {
                setSearchHistory(JSON.parse(stored));
            }
        } catch (e) {
            console.error('Error loading search history:', e);
        }
    }, []);

    // Load product frequency from localStorage
    useEffect(() => {
        try {
            const stored = localStorage.getItem(FAVORITES_KEY);
            if (stored) {
                setProductFrequency(JSON.parse(stored));
            }
        } catch (e) {
            console.error('Error loading product frequency:', e);
        }
    }, []);

    // Update product frequency after sale
    const updateProductFrequency = useCallback((soldItems: CartItem[]) => {
        setProductFrequency(prev => {
            const updated = { ...prev };
            soldItems.forEach(item => {
                updated[item.product.id] = (updated[item.product.id] || 0) + item.quantity;
            });
            localStorage.setItem(FAVORITES_KEY, JSON.stringify(updated));
            return updated;
        });
    }, []);

    // Search suggestions (limited to 8)
    const searchSuggestions = useMemo(() => {
        if (!searchQuery || searchQuery.length < 2) return [];
        const query = searchQuery.toLowerCase();
        return products
            .filter(p =>
                p.name.toLowerCase().includes(query) ||
                (p.barcode && p.barcode.toLowerCase().includes(query)) ||
                (p.sku && p.sku.toLowerCase().includes(query))
            )
            .slice(0, 8);
    }, [products, searchQuery]);

    // Reset selected index when suggestions change
    useEffect(() => {
        setSelectedSuggestionIndex(-1);
    }, [searchSuggestions]);

    // Save search to history
    const saveToHistory = useCallback((term: string) => {
        if (!term.trim()) return;
        setSearchHistory(prev => {
            const filtered = prev.filter(h => h.toLowerCase() !== term.toLowerCase());
            const newHistory = [term, ...filtered].slice(0, 10);
            localStorage.setItem(SEARCH_HISTORY_KEY, JSON.stringify(newHistory));
            return newHistory;
        });
    }, []);

    // Clear search history
    const clearSearchHistory = useCallback(() => {
        setSearchHistory([]);
        localStorage.removeItem(SEARCH_HISTORY_KEY);
    }, []);

    // Format price in CLP
    const formatPrice = useCallback((price: number) => {
        return new Intl.NumberFormat('es-CL', {
            style: 'currency',
            currency: 'CLP',
        }).format(price);
    }, []);

    // Filter products (with favorites support: selectedCategory === -1)
    const filteredProducts = useMemo(() => {
        let result = products.filter(p => {
            // If Favorites is selected, ignore standard filters to show all potential favorites
            if (selectedCategory === -1) return true;

            const matchesSearch = !searchQuery ||
                p.name.toLowerCase().includes(searchQuery.toLowerCase()) ||
                (p.barcode && p.barcode.toLowerCase().includes(searchQuery.toLowerCase())) ||
                (p.sku && p.sku.toLowerCase().includes(searchQuery.toLowerCase()));

            // -1 means "Favoritos" - show all products that have been sold
            const matchesCategory = selectedCategory === null ||
                selectedCategory === -1 ||
                p.category_id === selectedCategory;

            return matchesSearch && matchesCategory;
        });

        // If Favoritos is selected, filter to only products with sales and sort by frequency
        if (selectedCategory === -1) {
            result = result
                .filter(p => (productFrequency[p.id] || 0) > 0)
                .sort((a, b) => (productFrequency[b.id] || 0) - (productFrequency[a.id] || 0));
        }

        return result;
    }, [products, searchQuery, selectedCategory, productFrequency]);

    // Sales Return Logic
    const getReturnableQuantity = (sale: SaleRecord, productId: number, originalQty: number) => {
        const alreadyReturned = (sale.returned_quantities || {})[productId] || 0;
        return Math.max(0, originalQty - alreadyReturned);
    };

    const handleTicketLookup = async (e: React.FormEvent) => {
        e.preventDefault();

        if (!returnTicketId.trim()) {
            toast.error('Ingresa un número de ticket');
            return;
        }

        // First check sessionSales (for immediate access)
        const foundInSession = sessionSales.find(s => s.receipt_number == returnTicketId);
        if (foundInSession) {
            setReturnTicketData(foundInSession);
            const initialReturns: Record<string, number> = {};
            foundInSession.items.forEach(item => {
                initialReturns[item.name] = 0;
            });
            setReturnItems(initialReturns);
            return;
        }

        // If not in session, fetch from database
        try {
            const response = await fetch(tRoute('pos.sale.receipt', { receipt: returnTicketId }));
            if (!response.ok) {
                const data = await response.json();
                toast.error(data.error || 'Ticket no encontrado');
                setReturnTicketData(null);
                return;
            }

            const saleData = await response.json();

            // Convert to SaleRecord format
            const saleRecord: SaleRecord = {
                receipt_number: saleData.receipt_number,
                total: saleData.total,
                time: saleData.time,
                payment_method: saleData.payment_method,
                payment_label: saleData.payment_method === 'cash' ? 'Efectivo' :
                              saleData.payment_method === 'debit' ? 'Débito' :
                              saleData.payment_method === 'credit' ? 'Crédito' : 'Transferencia',
                change: saleData.change,
                items: saleData.items,
                returned_quantities: saleData.returned_quantities || {},
                is_fully_returned: saleData.is_fully_returned,
            };

            setReturnTicketData(saleRecord);
            const initialReturns: Record<string, number> = {};
            saleRecord.items.forEach(item => {
                initialReturns[item.name] = 0;
            });
            setReturnItems(initialReturns);
            toast.success('Ticket encontrado');
        } catch (error) {
            console.error('Error fetching ticket:', error);
            toast.error('Error al buscar el ticket');
            setReturnTicketData(null);
        }
    };

    const handleReturnItemChange = (itemName: string, qty: number, maxQty: number) => {
        if (qty < 0) qty = 0;
        if (qty > maxQty) qty = maxQty;
        setReturnItems(prev => ({
            ...prev,
            [itemName]: qty
        }));
    };

    const calculateRefundTotal = () => {
        if (!returnTicketData) return 0;
        return returnTicketData.items.reduce((total, item) => {
            const qty = returnItems[item.name] || 0;
            return total + (qty * item.unit_price);
        }, 0);
    };

    const confirmReturn = async () => {
        const refundTotal = calculateRefundTotal();
        if (refundTotal <= 0) {
            toast.error('Seleccione al menos un ítem para devolver');
            return;
        }

        if (!returnTicketData) return;

        if (!confirm(`¿Confirmas la devolución de ítems por un monto total de ${formatPrice(refundTotal)} de la Venta ID #${returnTicketData?.receipt_number}?`)) {
            return;
        }

        // Build refund items for API call
        // We need to find sale_item_ids. Since the API expects sale_item IDs and we only have product info,
        // we need to first fetch the full sale data with item IDs
        try {
            // First fetch the sale to get sale_item IDs
            const saleResponse = await fetch(tRoute('pos.sale.receipt', { receipt: returnTicketData.receipt_number }));
            if (!saleResponse.ok) {
                toast.error('Error al obtener datos de la venta');
                return;
            }
            const saleDataFull = await saleResponse.json();

            // We need to get the sale_item_id from the database. Since getSaleByReceipt doesn't return them,
            // let's use getSale by sale ID (we have it in saleDataFull.id)
            const saleDetailResponse = await fetch(tRoute('pos.sale.show', { sale: saleDataFull.id }));
            if (!saleDetailResponse.ok) {
                toast.error('Error al obtener detalles de la venta');
                return;
            }
            const saleDetails = await saleDetailResponse.json();

            // Build refund items array
            const refundItems: Array<{ sale_item_id: number; quantity: number }> = [];

            returnTicketData.items.forEach(item => {
                const qty = returnItems[item.name] || 0;
                if (qty > 0) {
                    // Find the sale_item_id from saleDetails
                    const saleItem = saleDetails.items.find((si: any) => si.product_id === item.product_id);
                    if (saleItem) {
                        refundItems.push({
                            sale_item_id: saleItem.id,
                            quantity: qty,
                        });
                    }
                }
            });

            if (refundItems.length === 0) {
                toast.error('No se encontraron ítems válidos para devolver');
                return;
            }

            // Call refund API using axios (configured with CSRF in Laravel)
            const response = await window.axios.post(tRoute('pos.refund'), {
                sale_id: saleDataFull.id,
                items: refundItems,
            });

            const refundResult = response.data;

            if (!refundResult.success) {
                toast.error(refundResult.error || 'Error al procesar la devolución');
                return;
            }

            // Update local stock (frontend state)
            setProducts(prevProducts => prevProducts.map(p => {
                const ticketItem = returnTicketData?.items.find(item => item.product_id === p.id);
                if (ticketItem) {
                    const returnQty = returnItems[ticketItem.name] || 0;
                    if (returnQty > 0) {
                        return { ...p, stock: p.stock + returnQty };
                    }
                }
                return p;
            }));

            // Update session sales with returned quantities
            setSessionSales(prevSales => prevSales.map(sale => {
                if (sale.receipt_number === returnTicketData?.receipt_number) {
                    const updatedReturns = { ...(sale.returned_quantities || {}) };
                    returnTicketData.items.forEach(item => {
                        const newReturnQty = returnItems[item.name] || 0;
                        if (newReturnQty > 0) {
                            updatedReturns[item.product_id] = (updatedReturns[item.product_id] || 0) + newReturnQty;
                        }
                    });
                    return {
                        ...sale,
                        returned_quantities: updatedReturns,
                        total: refundResult.new_total,
                    };
                }
                return sale;
            }));

            toast.success(`Devolución procesada: ${formatPrice(refundResult.total_refunded)} devueltos`);
            setShowReturnModal(false);
            setReturnTicketData(null);
            setReturnTicketId('');
            setReturnItems({});

        } catch (error) {
            console.error('Refund error:', error);
            toast.error('Error al procesar la devolución');
        }
    };

    // Calculate rows for virtualization
    const rowCount = Math.ceil(filteredProducts.length / columnCount);

    // Virtualizer for rows
    const rowVirtualizer = useVirtualizer({
        count: rowCount,
        getScrollElement: () => gridContainerRef.current,
        estimateSize: () => 300, // Increased to prevent overlap
        overscan: 3,
        gap: 16, // Increased gap between rows
    });

    // Cart calculations
    const cartTotal = useMemo(() => {
        return cart.reduce((sum, item) => sum + (item.product.price * item.quantity), 0);
    }, [cart]);

    const cartItemCount = useMemo(() => {
        return cart.reduce((sum, item) => sum + item.quantity, 0);
    }, [cart]);

    // Add to cart
    const addToCart = useCallback((product: Product) => {
        if (product.stock <= 0) {
            playError();
            toast.error(`Out of stock: ${product.name}`);
            return;
        }

        setCart(prevCart => {
            const existingItem = prevCart.find(item => item.product.id === product.id);

            if (existingItem) {
                if (existingItem.quantity >= product.stock) {
                    playError();
                    toast.error(`Max stock reached for: ${product.name}`);
                    setStockError(product.id);
                    setTimeout(() => setStockError(null), 4000);
                    return prevCart;
                }
                playScan(); // Play scan sound on successful quantity increase
                return prevCart.map(item =>
                    item.product.id === product.id
                        ? { ...item, quantity: item.quantity + 1 }
                        : item
                );
            }

            return [...prevCart, { product, quantity: 1 }];
        });
    }, [playError, playScan]);

    // Update quantity
    const updateQuantity = useCallback((productId: number, delta: number) => {
        setCart(prevCart => {
            return prevCart.map(item => {
                if (item.product.id === productId) {
                    const newQty = item.quantity + delta;
                    if (newQty <= 0) return null;
                    if (newQty > item.product.stock) {
                        setStockError(productId);
                        setTimeout(() => setStockError(null), 4000);
                        return item;
                    }
                    return { ...item, quantity: newQty };
                }
                return item;
            }).filter(Boolean) as CartItem[];
        });
    }, []);

    // Remove from cart
    const removeFromCart = useCallback((productId: number) => {
        setCart(prevCart => prevCart.filter(item => item.product.id !== productId));
    }, []);

    // Clear cart
    const clearCart = () => {
        if (cart.length === 0) return;
        if (confirm('¿Limpiar el carrito?')) {
            setCart([]);
        }
    };

    // Handle keyboard navigation in search
    const handleSearchKeyDown = (e: React.KeyboardEvent) => {
        const itemCount = searchSuggestions.length;

        switch (e.key) {
            case 'ArrowDown':
                e.preventDefault();
                setSelectedSuggestionIndex(prev =>
                    prev < itemCount - 1 ? prev + 1 : 0
                );
                break;
            case 'ArrowUp':
                e.preventDefault();
                setSelectedSuggestionIndex(prev =>
                    prev > 0 ? prev - 1 : itemCount - 1
                );
                break;
            case 'Enter':
                e.preventDefault();
                if (selectedSuggestionIndex >= 0 && searchSuggestions[selectedSuggestionIndex]) {
                    // Add selected suggestion to cart
                    const product = searchSuggestions[selectedSuggestionIndex];
                    addToCart(product);
                    saveToHistory(product.name);
                    setSearchQuery('');
                    setShowSuggestions(false);
                } else if (searchQuery) {
                    // Try barcode/SKU exact match
                    const product = products.find(p =>
                        p.barcode?.toLowerCase() === searchQuery.toLowerCase() ||
                        p.sku?.toLowerCase() === searchQuery.toLowerCase()
                    );
                    if (product) {
                        addToCart(product);
                        saveToHistory(product.name);
                        setSearchQuery('');
                        setShowSuggestions(false);
                    }
                }
                break;
            case 'Escape':
                setShowSuggestions(false);
                setSelectedSuggestionIndex(-1);
                break;
        }
    };

    // Handle selecting a suggestion
    const handleSelectSuggestion = (product: Product) => {
        addToCart(product);
        saveToHistory(product.name);
        setSearchQuery('');
        setShowSuggestions(false);
    };

    // Handle selecting from history
    const handleSelectHistory = (term: string) => {
        setSearchQuery(term);
        setShowSuggestions(true);
    };

    // Process sale
    const processSale = async () => {
        if (cart.length === 0) return;

        const paid = paymentMethod === 'cash' ? parseFloat(paidAmount) || cartTotal : cartTotal;


        // Validate amounts
        if (isSplitPayment) {
            const totalPaid = paymentSplits.reduce((sum, s) => sum + s.amount, 0);
            if (totalPaid < cartTotal) {
                alert('El monto total de los pagos es insuficiente');
                return;
            }
        } else {
            const paid = parseFloat(paidAmount) || 0;
            if (paymentMethod === 'cash' && paid < cartTotal) {
                alert('Monto insuficiente');
                return;
            }
        }

        // Prepare payment data
        let finalPayments: PaymentSplit[] = [];
        let totalPaid = 0;
        let primaryMethod = paymentMethod;

        if (isSplitPayment) {
            finalPayments = paymentSplits;
            totalPaid = paymentSplits.reduce((sum, s) => sum + s.amount, 0);
            primaryMethod = paymentSplits[0]?.method || 'cash';
        } else {
            const amount = paymentMethod === 'cash' ? (parseFloat(paidAmount) || 0) : cartTotal;
            finalPayments = [{ method: paymentMethod, amount: amount }];
            totalPaid = amount;
            primaryMethod = paymentMethod;
        }

        const change = Math.max(0, totalPaid - cartTotal);

        setProcessing(true);
        setSaleResult(null);
        const startTime = Date.now();

        router.post(tRoute('pos.store'), {
            items: cart.map(item => ({
                product_id: item.product.id,
                quantity: item.quantity,
                unit_price: item.product.price,
            })),
            payment_method: primaryMethod,
            paid_amount: totalPaid,
            payments: JSON.stringify(finalPayments), // Send as JSON string for Inertia
        }, {
            preserveScroll: true,
            onSuccess: (page: any) => {
                const flash = page.props.flash;
                if (flash?.success) {
                    const elapsed = Date.now() - startTime;
                    const minDelay = 3000; // Minimum 3 seconds
                    const remainingDelay = Math.max(0, minDelay - elapsed);

                    // Save cart items before clearing
                    // Save cart items before clearing
                    const ticketItems = cart.map(item => ({
                        product_id: item.product.id,
                        name: item.product.name,
                        quantity: item.quantity,
                        unit_price: item.product.price,
                        subtotal: item.product.price * item.quantity,
                        image: item.product.image
                    }));

                    // Optimistically update stock (decrement)
                    setProducts(prevProducts => prevProducts.map(p => {
                        const cartItem = cart.find(c => c.product.id === p.id);
                        if (cartItem) {
                            return { ...p, stock: p.stock - cartItem.quantity };
                        }
                        return p;
                    }));

                    setTimeout(() => {
                        setSaleResult({
                            success: true,
                            receipt_number: flash.receipt_number,
                            total: flash.total,
                            change: change,
                        });
                        setTicketData({
                            receipt_number: flash.receipt_number,
                            total: flash.total,
                            change: change,
                            payment_method: primaryMethod,
                            payments: [...paymentSplits], // Include split payments
                            date: new Date().toLocaleString('es-CL'),
                            items: ticketItems,
                        });

                        // Add to session history
                        setSessionSales(prev => [{
                            receipt_number: flash.receipt_number.toString(),
                            total: flash.total,
                            time: new Date().toLocaleTimeString('es-CL', { hour: '2-digit', minute: '2-digit' }),
                            payment_method: primaryMethod,
                            payment_label: primaryMethod === 'cash' ? 'Efectivo' :
                                         primaryMethod === 'debit' ? 'Débito' :
                                         primaryMethod === 'credit' ? 'Crédito' : 'Transferencia',
                            change: change,
                            items: ticketItems,
                            payments: [...paymentSplits]
                        }, ...prev]);

                        updateProductFrequency(cart); // Track for favorites
            // Play success sound
            playSale();

            // 2. Clear Cart & Reset State
            setCart([]);
                        setPaidAmount('');
                        setPaymentSplits([]); // Reset splits
                        setSplitAmount('');
                        setProcessing(false);
                        setShowPaymentModal(false);
                        setSaleResult(null);
                        setShowTicketDrawer(true);
                    }, remainingDelay);
                }
            },
            onError: (errors) => {
                setProcessing(false);
                const errorMsg = Object.values(errors).join(', ');
                setSaleError(errorMsg);

                // Auto close after showing error for 3 seconds
                setTimeout(() => {
                    setSaleError(null);
                    setShowPaymentModal(false);
                }, 3000);
            },
        });
    };

    // Calculate change
    const changeAmount = useMemo(() => {
        const paid = parseFloat(paidAmount) || 0;
        return Math.max(0, paid - cartTotal);
    }, [paidAmount, cartTotal]);

    return (
        <AuthenticatedLayout className="h-screen" isFullMain absoluteNav>
            <Head title="Punto de Venta" />

            {/* Main Content - Full height */}
            <div className="grid grid-cols-12 h-full overflow-hidden">
                {/* Products Section */}
                <div className="col-span-9 flex flex-col bg-gray-50">
                    {/* Search & Categories */}
                    <div className="p-4 bg-white border-b border-gray-200 pt-[77px]">
                        <div className="flex gap-4 items-center justify-between mb-4">
                            <div className="relative flex-1">
                                <Search className="absolute left-3 top-1/2 -translate-y-1/2 w-5 h-5 text-gray-400 z-10" />
                                <input
                                    ref={searchInputRef}
                                    type="text"
                                    placeholder="Escanear código o buscar producto..."
                                    value={searchQuery}
                                    onChange={(e) => {
                                        setSearchQuery(e.target.value);
                                        setShowSuggestions(true);
                                    }}
                                    onFocus={() => setShowSuggestions(true)}
                                    onBlur={() => {
                                        setTimeout(() => setShowSuggestions(false), 200);
                                    }}
                                    onKeyDown={handleSearchKeyDown}
                                    className="w-full pl-10 pr-4 py-2 placeholder:text-sm border border-gray-200 rounded-xl text-lg focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary"
                                />

                                {/* Suggestions Dropdown */}
                                {showSuggestions && (searchQuery.length >= 2 || searchHistory.length > 0) && (
                                    <div className="absolute top-full left-0 right-0 mt-1 bg-white rounded-xl shadow-lg border border-gray-200 z-50 max-h-[400px] overflow-auto">
                                        {/* History Section - when no query */}
                                        {searchQuery.length < 2 && searchHistory.length > 0 && (
                                            <div>
                                                <div className="px-3 py-2 text-xs text-gray-500 flex justify-between items-center border-b border-gray-100">
                                                    <span className="font-medium">Búsquedas recientes</span>
                                                    <button
                                                        onMouseDown={(e) => e.preventDefault()}
                                                        onClick={(e) => {
                                                            e.preventDefault();
                                                            clearSearchHistory();
                                                        }}
                                                        className="text-primary hover:text-primary/80 cursor-pointer"
                                                    >
                                                        Limpiar
                                                    </button>
                                                </div>
                                                {searchHistory.map((term, idx) => (
                                                    <button
                                                        key={idx}
                                                        onMouseDown={(e) => e.preventDefault()}
                                                        onClick={() => handleSelectHistory(term)}
                                                        className="w-full px-4 py-2 text-left hover:bg-gray-50 flex items-center gap-3 text-gray-700"
                                                    >
                                                        <RotateCcw className="w-4 h-4 text-gray-400" />
                                                        <span className="truncate">{term}</span>
                                                    </button>
                                                ))}
                                            </div>
                                        )}

                                        {/* Suggestions Section */}
                                        {searchQuery.length >= 2 && searchSuggestions.length > 0 && (
                                            <div>
                                                {searchSuggestions.map((product, idx) => {
                                                    const cartItem = cart.find(item => item.product.id === product.id);
                                                    const quantityInCart = cartItem?.quantity || 0;

                                                    return (
                                                        <div
                                                            key={product.id}
                                                            className={`w-full px-3 py-2 flex items-center gap-3 transition-colors ${
                                                                idx === selectedSuggestionIndex
                                                                    ? 'bg-primary/10'
                                                                    : 'hover:bg-gray-50'
                                                            }`}
                                                        >
                                                            {/* Clickable product info */}
                                                            <button
                                                                onClick={() => handleSelectSuggestion(product)}
                                                                className="flex items-center gap-3 flex-1 min-w-0 text-left"
                                                            >
                                                                <div className="w-12 h-12 bg-gray-100 rounded-lg flex items-center justify-center overflow-hidden shrink-0">
                                                                    {product.image ? (
                                                                        <img
                                                                            src={product.image.startsWith('http') ? product.image : `/storage/${product.image}`}
                                                                            alt=""
                                                                            className="w-full h-full object-cover"
                                                                        />
                                                                    ) : (
                                                                        <span className="text-xl">📦</span>
                                                                    )}
                                                                </div>
                                                                <div className="flex-1 min-w-0">
                                                                    <p className="font-medium text-gray-900 truncate">{product.name}</p>
                                                                    <div className="flex items-center gap-2 text-sm">
                                                                        <span className="text-primary font-bold">{formatPrice(product.price)}</span>
                                                                        <span className={`${product.stock <= 10 ? 'text-danger' : 'text-gray-500'}`}>
                                                                            Stock: {product.stock}
                                                                        </span>
                                                                    </div>
                                                                </div>
                                                            </button>

                                                            {/* Quantity controls */}
                                                            <div className="shrink-0 flex items-center gap-1">
                                                                {quantityInCart > 0 ? (
                                                                    <>
                                                                        <button
                                                                            onMouseDown={(e) => e.preventDefault()}
                                                                            onClick={(e) => {
                                                                                e.stopPropagation();
                                                                                updateQuantity(product.id, -1);
                                                                            }}
                                                                            className="w-8 h-8 rounded-lg bg-gray-100 hover:bg-gray-200 flex items-center justify-center transition-colors"
                                                                        >
                                                                            <Minus className="w-4 h-4" />
                                                                        </button>
                                                                        <div className="flex flex-col items-center min-w-[32px]">
                                                                            <span className="font-bold text-primary">
                                                                                {quantityInCart}
                                                                            </span>
                                                                            {quantityInCart >= product.stock && (
                                                                                <span className="text-[10px] text-danger font-medium leading-none">
                                                                                    Máx
                                                                                </span>
                                                                            )}
                                                                        </div>
                                                                        <button
                                                                            onMouseDown={(e) => e.preventDefault()}
                                                                            onClick={(e) => {
                                                                                e.stopPropagation();
                                                                                addToCart(product);
                                                                            }}
                                                                            disabled={quantityInCart >= product.stock}
                                                                            className={`w-8 h-8 rounded-lg flex items-center justify-center transition-colors ${
                                                                                quantityInCart >= product.stock
                                                                                    ? 'bg-gray-100 text-gray-400 cursor-not-allowed'
                                                                                    : 'bg-primary text-white hover:bg-primary/90'
                                                                            }`}
                                                                        >
                                                                            {quantityInCart >= product.stock ? (
                                                                                <Check className="w-4 h-4" />
                                                                            ) : (
                                                                                <Plus className="w-4 h-4" />
                                                                            )}
                                                                        </button>
                                                                    </>
                                                                ) : (
                                                                    <button
                                                                        onMouseDown={(e) => e.preventDefault()}
                                                                        onClick={(e) => {
                                                                            e.stopPropagation();
                                                                            addToCart(product);
                                                                            saveToHistory(product.name);
                                                                        }}
                                                                        disabled={product.stock <= 0}
                                                                        className="w-10 h-10 rounded-lg bg-primary text-white hover:bg-primary/90 flex items-center justify-center transition-colors disabled:opacity-50"
                                                                    >
                                                                        <Plus className="w-5 h-5" />
                                                                    </button>
                                                                )}
                                                            </div>
                                                        </div>
                                                    );
                                                })}
                                            </div>
                                        )}

                                        {/* No results */}
                                        {searchQuery.length >= 2 && searchSuggestions.length === 0 && (
                                            <div className="px-4 py-6 text-center text-gray-500">
                                                No se encontraron productos
                                            </div>
                                        )}
                                    </div>
                                )}
                            </div>
                            <div className="flex items-center gap-2">
                                <button
                                    onClick={() => setShowHistoryDrawer(true)}
                                    className="p-2 bg-gray-100 hover:bg-gray-200 rounded-lg transition-colors text-gray-600 relative"
                                    title="Historial de Ventas"
                                >
                                    <History className="w-5 h-5" />
                                    {sessionSales.length > 0 && (
                                        <span className="absolute -top-1 -right-1 flex h-3 w-3">
                                            <span className="animate-ping absolute inline-flex h-full w-full rounded-full bg-amber-400 opacity-75"></span>
                                            <span className="relative inline-flex rounded-full h-3 w-3 bg-amber-500"></span>
                                        </span>
                                    )}
                                </button>
                                <button
                                    onClick={() => setShowReturnModal(true)}
                                    className="p-2 bg-gray-100 hover:bg-gray-200 rounded-lg transition-colors text-gray-600 ml-2"
                                    title="Anular / Devolución"
                                >
                                    <RotateCcw className="w-5 h-5" />
                                </button>
                                <div className="text-sm text-gray-500 bg-gray-100 px-4 py-2 rounded-lg">
                                    Ticket #{nextReceiptNumber}
                                </div>
                            </div>
                        </div>

                        {/* History Drawer */}
                        <Drawer
                            isOpen={showHistoryDrawer}
                            onClose={() => setShowHistoryDrawer(false)}
                            title="Historial de Ventas (Sesión)"
                            position="right"
                        >
                            <div className="flex flex-col h-full">
                                {sessionSales.length === 0 ? (
                                    <div className="flex-1 flex flex-col items-center justify-center text-gray-400 p-8">
                                        <History className="w-16 h-16 mb-4 opacity-20" />
                                        <p className="text-center">No hay ventas registradas en esta sesión.</p>
                                    </div>
                                ) : (
                                    <div className="flex-1 overflow-y-auto p-4 space-y-3">
                                        <div className="flex justify-between items-center mb-2 px-1">
                                            <span className="text-xs font-medium text-gray-500 uppercase">Hoy</span>
                                            <span className="text-xs font-medium text-gray-400">{sessionSales.length} ventas</span>
                                        </div>

                                        {sessionSales.map((sale, idx) => (
                                            <div key={idx} className="bg-white border border-gray-100 rounded-xl p-4 shadow-sm hover:shadow-md transition-shadow">
                                                <div className="flex justify-between items-start mb-3">
                                                    <div>
                                                        <div className="flex items-center gap-2">
                                                            <span className="font-bold text-gray-900">#{sale.receipt_number}</span>
                                                            <span className="text-xs text-gray-400 bg-gray-50 px-1.5 py-0.5 rounded">{sale.time}</span>
                                                        </div>
                                                        {/* Item Images Stack */}
                                                        <div className="flex items-center -space-x-2 mt-2">
                                                            {sale.items.slice(0, 4).map((item, itemIdx) => (
                                                                <div
                                                                    key={itemIdx}
                                                                    className="w-8 h-8 rounded-full border-2 border-white bg-gray-100 overflow-hidden flex items-center justify-center shrink-0 z-0 relative"
                                                                    style={{ zIndex: 4 - itemIdx }}
                                                                    title={`${item.quantity}x ${item.name} [${item.image ? 'Has Image' : 'No Image'}]`}
                                                                >
                                                                    {item.image ? (
                                                                        <img
                                                                            src={item.image.startsWith('http') ? item.image : `/storage/${item.image}`}
                                                                            alt={item.name}
                                                                            className="w-full h-full object-cover"
                                                                        />
                                                                    ) : (
                                                                        <Grid3X3 className="w-4 h-4 text-gray-400" />
                                                                    )}
                                                                </div>
                                                            ))}
                                                            {sale.items.length > 4 && (
                                                                <div className="w-8 h-8 rounded-full border-2 border-white bg-gray-100 flex items-center justify-center shrink-0 z-0 text-[10px] font-bold text-gray-500">
                                                                    +{sale.items.length - 4}
                                                                </div>
                                                            )}
                                                        </div>
                                                    </div>
                                                    <div className="text-right">
                                                        <div className="font-bold text-lg text-primary">{formatPrice(sale.total)}</div>
                                                    </div>
                                                </div>

                                                <div className="border-t border-gray-50 pt-3 flex justify-between items-center">
                                                    <div className="flex items-center gap-1.5 text-xs text-gray-600">
                                                        <span>{sale.payment_label}</span>
                                                        {sale.payments && sale.payments.length > 1 && (
                                                            <span className="bg-gray-100 text-gray-500 px-1 rounded text-[10px]">Split</span>
                                                        )}
                                                    </div>

                                                    <button
                                                        onClick={() => {
                                                            setTicketData({
                                                                receipt_number: parseInt(sale.receipt_number),
                                                                total: sale.total,
                                                                change: sale.change,
                                                                payment_method: sale.payment_method,
                                                                payments: sale.payments,
                                                                date: sale.time, // Using time as display date for reprint
                                                                items: sale.items
                                                            });
                                                            setShowTicketDrawer(true);
                                                            setShowHistoryDrawer(false);
                                                        }}
                                                        className="text-xs font-medium text-primary hover:text-primary/80 flex items-center gap-1 px-2 py-1 hover:bg-primary/5 rounded transition"
                                                    >
                                                        <Printer className="w-3.5 h-3.5" />
                                                        Reimprimir
                                                    </button>
                                                </div>
                                            </div>
                                        ))}
                                    </div>
                                )}
                            </div>
                        </Drawer>

                        {/* Categories Carousel */}
                        <div className="relative" style={{ overflow: 'clip' }}>
                            {/* Left Arrow with Gradient - Floating */}
                            <div className="absolute left-0 top-0 bottom-0 z-20 flex items-center pl-1 pr-6 bg-gradient-to-r from-white via-white/90 to-transparent">
                                <button
                                    onClick={() => {
                                        const container = document.getElementById('categories-container');
                                        if (container) container.scrollBy({ left: -200, behavior: 'smooth' });
                                    }}
                                    className="p-2 rounded-full bg-white border border-gray-200 hover:bg-gray-100 transition "
                                >
                                    <ChevronLeft className="w-4 h-4 text-gray-600" />
                                </button>
                            </div>

                            {/* Categories Container */}
                            <div
                                id="categories-container"
                                className="flex gap-2 overflow-x-auto scrollbar-hide scroll-smooth px-12"
                                style={{ scrollbarWidth: 'none', msOverflowStyle: 'none' }}
                            >
                                <button
                                    onClick={() => handleCategorySelect(null)}
                                    className={`shrink-0 px-4 py-2 rounded-full text-sm font-medium whitespace-nowrap transition ${
                                        selectedCategory === null
                                            ? 'bg-primary text-white shadow-md transform scale-105'
                                            : 'bg-white text-gray-600 hover:bg-gray-50 border border-gray-200'
                                    }`}
                                >
                                    Todos
                                </button>
                                {categories.map((category) => (
                                    <button
                                        key={category.id}
                                        onClick={() => handleCategorySelect(category.id)}
                                        className={`shrink-0 px-4 py-2 rounded-full text-sm font-medium whitespace-nowrap transition ${
                                            selectedCategory === category.id
                                                ? 'bg-primary text-white shadow-md transform scale-105'
                                                : 'bg-white text-gray-600 hover:bg-gray-50 border border-gray-200'
                                        }`}
                                    >
                                        {category.name}
                                    </button>
                                ))}
                            </div>

                            {/* Right Arrow with Gradient - Floating */}
                            <div className="absolute right-0 top-0 bottom-0 z-20 flex items-center pr-1 pl-6 bg-gradient-to-l from-white via-white/90 to-transparent">
                                <button
                                    onClick={() => {
                                        const container = document.getElementById('categories-container');
                                        if (container) container.scrollBy({ left: 200, behavior: 'smooth' });
                                    }}
                                    className="p-2 rounded-full bg-white border border-gray-200 hover:bg-gray-100 transition "
                                >
                                    <ChevronRight className="w-4 h-4 text-gray-600" />
                                </button>
                            </div>
                        </div>
                    </div>

                    {/* Virtualized Products Grid */}
                    <div
                        ref={gridContainerRef}
                        className="flex-1 overflow-auto p-4"
                        style={{ contain: 'strict' }}
                    >
                        <div
                            style={{
                                height: `${rowVirtualizer.getTotalSize()}px`,
                                width: '100%',
                                position: 'relative',
                            }}
                        >
                            {rowVirtualizer.getVirtualItems().map((virtualRow) => {
                                const startIndex = virtualRow.index * columnCount;
                                const rowProducts = filteredProducts.slice(startIndex, startIndex + columnCount);

                                return (
                                    <div
                                        key={virtualRow.key}
                                        style={{
                                            position: 'absolute',
                                            top: 0,
                                            left: 0,
                                            width: '100%',
                                            height: `${virtualRow.size}px`,
                                            transform: `translateY(${virtualRow.start}px)`,
                                        }}
                                        className="grid gap-3"
                                        data-style-grid-cols={columnCount}
                                    >
                                        <div
                                            className="grid gap-3"
                                            style={{
                                                gridTemplateColumns: `repeat(${columnCount}, minmax(0, 1fr))`,
                                            }}
                                        >
                                            {rowProducts.map((product, colIndex) => (
                                                <button
                                                    key={product.id}
                                                    onClick={() => addToCart(product)}
                                                    disabled={product.stock <= 0}
                                                    className={`bg-glass rounded-[12px] p-3 text-left shadow-panel hover:shadow-md transition-all border animate-in fade-in slide-in-from-bottom-4 duration-300 ${
                                                        product.stock <= 0 ? 'opacity-50 cursor-not-allowed' : 'hover:border-primary hover:scale-[1.02]'
                                                    }`}
                                                    style={{
                                                        animationDelay: `${colIndex * 30}ms`,
                                                        animationFillMode: 'both',
                                                        animationTimingFunction: 'cubic-bezier(0.1,0.7,0.5,1)'
                                                    }}
                                                >
                                                    <div className="h-[135px] bg-gray-100 object-contain rounded-lg mb-2 flex items-center justify-center overflow-hidden">
                                                        {product.image ? (
                                                            <BlurImage
                                                                src={product.image.startsWith('http') ? product.image : `/storage/${product.image}`}
                                                                alt={product.name}
                                                                className="w-full h-full object-cover"
                                                                blur={true}
                                                            />
                                                        ) : (
                                                            <span className="text-3xl">📦</span>
                                                        )}
                                                    </div>
                                                    <h3 className="font-medium text-gray-900 text-sm line-clamp-2 mb-1">
                                                        {product.name}
                                                    </h3>
                                                    <p className="text-primary font-bold">
                                                        {formatPrice(product.price)}
                                                    </p>
                                                    <p className={`text-xs ${product.stock <= 10 ? 'text-danger' : 'text-gray-500'}`}>
                                                        Stock: {product.stock}
                                                    </p>
                                                </button>
                                            ))}
                                        </div>
                                    </div>
                                );
                            })}
                        </div>
                    </div>
                </div>

                {/* Cart Section - Fixed height to fit viewport */}
                <div className="col-span-3 bg-white border-l border-gray-200 flex flex-col pt-16 h-[calc(100vh-0px)]">
                    {/* Cart Header */}
                    <div className="p-4 border-b border-gray-100 flex items-center justify-between">
                        <h2 className="font-semibold text-gray-900 flex items-center gap-2">
                            <ShoppingCart className="w-5 h-5" />
                            Carrito ({cartItemCount})
                        </h2>
                        <DropdownMenu>
                            <DropdownMenuTrigger asChild>
                                <button
                                    disabled={cart.length === 0}
                                    className="p-2 text-gray-400 hover:text-danger hover:bg-danger/10 rounded-lg transition disabled:opacity-50 disabled:cursor-not-allowed"
                                    title="Vaciar Carrito"
                                >
                                    <Trash2 className="w-5 h-5" />
                                </button>
                            </DropdownMenuTrigger>
                            <DropdownMenuContent align="end" className="w-56">
                                <DropdownMenuLabel>¿Vaciar el carrito?</DropdownMenuLabel>
                                <DropdownMenuSeparator />
                                <DropdownMenuItem
                                    className="text-danger focus:text-danger focus:bg-danger/10 cursor-pointer"
                                    onClick={clearCart}
                                >
                                    <Trash2 className="mr-2 h-4 w-4" />
                                    <span>Sí, vaciar todo</span>
                                </DropdownMenuItem>
                                <DropdownMenuItem className="cursor-pointer">
                                    <X className="mr-2 h-4 w-4" />
                                    <span>No, conservar</span>
                                </DropdownMenuItem>
                            </DropdownMenuContent>
                        </DropdownMenu>
                    </div>

                    {/* Cart Items */}
                    <div className=" overflow-y-auto p-4">
                        {cart.length === 0 ? (
                            <div className="text-center text-gray-400 py-8">
                                <ShoppingCart className="w-12 h-12 mx-auto mb-2 opacity-50" />
                                <p>Carrito vacío</p>
                            </div>
                        ) : (
                            <div className="space-y-3">
                                {cart.map(item => (
                                    <div
                                        key={item.product.id}
                                        className={`relative flex items-center gap-3 p-3 rounded-lg transition-all ${
                                            stockError === item.product.id
                                                ? 'bg-danger/10 outline outline-2 outline-danger'
                                                : 'bg-gray-50'
                                        }`}
                                    >
                                        {/* Stock Error Tooltip */}
                                        {stockError === item.product.id && (
                                            <div className="absolute -top-8 left-1/2 -translate-x-1/2 px-3 py-1 bg-danger text-white text-xs rounded-lg shadow-lg whitespace-nowrap z-10">
                                                Stock insuficiente (máx: {item.product.stock})
                                                <div className="absolute -bottom-1 left-1/2 -translate-x-1/2 w-2 h-2 bg-danger rotate-45" />
                                            </div>
                                        )}
                                        <div className="flex-1 min-w-0">
                                            <p className="font-medium text-gray-900 text-sm truncate">
                                                {item.product.name}
                                            </p>
                                            <p className="text-xs text-gray-500">
                                                {formatPrice(item.product.price)} c/u
                                            </p>
                                        </div>
                                        <div className="flex items-center gap-2">
                                            <button
                                                onClick={() => updateQuantity(item.product.id, -1)}
                                                className="w-8 h-8 rounded-lg bg-white border border-gray-200 flex items-center justify-center hover:bg-gray-100"
                                            >
                                                <Minus className="w-4 h-4" />
                                            </button>
                                            <span className={`w-8 text-center font-medium ${stockError === item.product.id ? 'text-danger' : ''}`}>
                                                {item.quantity}
                                            </span>
                                            <button
                                                onClick={() => updateQuantity(item.product.id, 1)}
                                                className={`w-8 h-8 rounded-lg bg-white border flex items-center justify-center hover:bg-gray-100 ${
                                                    stockError === item.product.id ? 'border-danger' : 'border-gray-200'
                                                }`}
                                            >
                                                <Plus className="w-4 h-4" />
                                            </button>
                                        </div>
                                        <div className="text-right">
                                            <p className="font-bold text-gray-900">
                                                {formatPrice(item.product.price * item.quantity)}
                                            </p>
                                        </div>
                                        <button
                                            onClick={() => removeFromCart(item.product.id)}
                                            className="p-1 text-gray-400 hover:text-danger"
                                        >
                                            <X className="w-4 h-4" />
                                        </button>
                                    </div>
                                ))}
                            </div>
                        )}
                    </div>

                    {/* Cart Footer - Always at bottom */}
                    <div className="mt-auto p-4 border-t border-gray-100">
                        {/* Totals */}
                        <div className="space-y-1 mb-4">
                            <div className="flex justify-between text-sm">
                                <span className="text-gray-600">Subtotal:</span>
                                <span className="font-medium">{formatPrice(cartTotal / 1.19)}</span>
                            </div>
                            <div className="flex justify-between text-sm">
                                <span className="text-gray-600">IVA (19%):</span>
                                <span className="font-medium">{formatPrice(cartTotal - cartTotal / 1.19)}</span>
                            </div>
                            <div className="flex justify-between pt-2 border-t border-gray-200 mt-2">
                                <span className="text-primary font-semibold">Total a Pagar:</span>
                                <span className="text-primary font-bold text-xl">{formatPrice(cartTotal)}</span>
                            </div>
                        </div>

                        {/* Payment Methods */}
                        <div className="grid grid-cols-4 gap-2 mb-4">
                            <button
                                onClick={() => { playClick(); setPaymentMethod('cash'); }}
                                className={`flex flex-col items-center justify-center p-3 rounded-lg border transition-all ${
                                paymentMethod === 'cash' ? 'border-primary bg-primary/10' : 'border-gray-200'
                                }`}
                            >
                                <CashIcon size={23} />
                                <span className={`text-xs font-medium ${paymentMethod === 'cash' ? 'text-primary' : 'text-gray-600'}`}>Efectivo</span>
                            </button>
                            <button
                                onClick={() => { playClick(); setPaymentMethod('debit'); }}
                                className={`flex flex-col items-center justify-center p-3 rounded-lg border transition-all ${
                                paymentMethod === 'debit' ? 'border-primary bg-primary/10' : 'border-gray-200'
                                }`}
                            >
                                <CreditCard className={`w-5 h-5 mb-1 ${paymentMethod === 'debit' ? 'text-primary' : 'text-gray-500'}`} />
                                <span className={`text-xs font-medium ${paymentMethod === 'debit' ? 'text-primary' : 'text-gray-600'}`}>Débito</span>
                            </button>
                            <button
                                onClick={() => { playClick(); setPaymentMethod('credit'); }}
                                className={`flex flex-col items-center justify-center p-3 rounded-lg border transition-all ${
                                paymentMethod === 'credit' ? 'border-primary bg-primary/10' : 'border-gray-200'
                                }`}
                            >
                                <CreditCard className={`w-5 h-5 mb-1 ${paymentMethod === 'credit' ? 'text-primary' : 'text-gray-500'}`} />
                                <span className={`text-xs font-medium ${paymentMethod === 'credit' ? 'text-primary' : 'text-gray-600'}`}>Crédito</span>
                            </button>
                            <button
                                onClick={() => { playClick(); setPaymentMethod('transfer'); }}
                                className={`flex flex-col items-center justify-center p-3 rounded-lg border transition-all ${
                                paymentMethod === 'transfer' ? 'border-primary bg-primary/10' : 'border-gray-200'
                                }`}
                            >
                                <ArrowLeftRight className={`w-5 h-5 mb-1 ${paymentMethod === 'transfer' ? 'text-primary' : 'text-gray-500'}`} />
                                <span className={`text-xs font-medium ${paymentMethod === 'transfer' ? 'text-primary' : 'text-gray-600'}`}>Transfer</span>
                            </button>
                        </div>

                        {/* Action Buttons */}
                        <div className="flex gap-2">
                            <button
                                onClick={() => setShowPaymentModal(true)}
                                disabled={cart.length === 0 || processing}
                                className="flex-1 py-3 bg-primary text-white font-semibold rounded-xl hover:bg-primary/90 transition disabled:opacity-50 disabled:cursor-not-allowed flex items-center justify-center gap-2"
                            >
                                <Check className="w-5 h-5" />
                                Cobrar
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            {/* Payment Modal */}
            {showPaymentModal && (
                <div className="fixed inset-0 bg-black/50 flex items-center justify-center z-50">
                    <div className="bg-white rounded-2xl shadow-xl max-w-md w-full mx-4 p-6">
                        {processing ? (
                            <div className="flex flex-col items-center justify-center py-8">
                                <Lottie
                                    animationData={loadingAnimation}
                                    loop={true}
                                    style={{ width: 150, height: 150 }}
                                />
                                <p className="text-lg font-medium text-gray-700 mt-4">Procesando venta...</p>
                            </div>
                        ) : saleError ? (
                            <div className="flex flex-col items-center justify-center py-8">
                                <Lottie
                                    animationData={errorAnimation}
                                    loop={false}
                                    style={{ width: 150, height: 150 }}
                                />
                                <h2 className="text-2xl font-bold text-danger mb-2">Error en la Venta</h2>
                                <p className="text-gray-600 text-center">{saleError}</p>
                            </div>
                        ) : (
                            <>
                                <h2 className="text-xl font-bold text-gray-900 mb-4">Confirmar Pago</h2>

                                <div className="flex p-1 bg-gray-100 rounded-xl mb-6">
                                    <button
                                        onClick={() => {
                                            setIsSplitPayment(false);
                                            setPaymentSplits([]);
                                            setSplitAmount('');
                                        }}
                                        className={`flex-1 py-2 text-sm font-medium rounded-lg transition-all shadow-sm ${
                                            !isSplitPayment
                                                ? 'bg-white text-primary shadow'
                                                : 'text-gray-500 hover:text-gray-700'
                                        }`}
                                    >
                                        Pago Único
                                    </button>
                                    <button
                                        onClick={() => setIsSplitPayment(true)}
                                        className={`flex-1 py-2 text-sm font-medium rounded-lg transition-all ${
                                            isSplitPayment
                                                ? 'bg-white text-primary shadow'
                                                : 'text-gray-500 hover:text-gray-700'
                                        }`}
                                    >
                                        Dividir Pago
                                    </button>
                                </div>

                                <div className="mb-4 space-y-1">
                                    <div className="flex justify-between text-sm">
                                        <span className="text-gray-600">Subtotal:</span>
                                        <span className="font-medium">{formatPrice(cartTotal / 1.19)}</span>
                                    </div>
                                    <div className="flex justify-between text-sm">
                                        <span className="text-gray-600">IVA (19%):</span>
                                        <span className="font-medium">{formatPrice(cartTotal - cartTotal / 1.19)}</span>
                                    </div>
                                    <div className="flex justify-between pt-2 border-t border-gray-200 mt-2">
                                        <span className="text-primary font-semibold">Total a Pagar:</span>
                                        <span className="text-primary font-bold text-2xl">{formatPrice(cartTotal)}</span>
                                    </div>
                                </div>

                                {isSplitPayment ? (
                                    /* Split Payment UI */
                                    <>
                                        {/* Split payments list */}
                                        {paymentSplits.length > 0 && (
                                            <div className="mb-4 p-3 bg-gray-50 rounded-xl space-y-2">
                                                <p className="text-xs font-medium text-gray-500 uppercase">Pagos agregados:</p>
                                                {paymentSplits.map((split, idx) => (
                                                    <div key={idx} className="flex justify-between items-center">
                                                        <div className="flex items-center gap-2">
                                                            {split.method === 'cash' && <Banknote className="w-4 h-4 text-green-600" />}
                                                            {split.method === 'debit' && <CreditCard className="w-4 h-4 text-blue-600" />}
                                                            {split.method === 'credit' && <CreditCard className="w-4 h-4 text-purple-600" />}
                                                            {split.method === 'transfer' && <ArrowLeftRight className="w-4 h-4 text-cyan-600" />}
                                                            <span className="text-sm capitalize">{
                                                                split.method === 'cash' ? 'Efectivo' :
                                                                split.method === 'debit' ? 'Débito' :
                                                                split.method === 'credit' ? 'Crédito' : 'Transferencia'
                                                            }</span>
                                                        </div>
                                                        <div className="flex items-center gap-2">
                                                            <span className="font-medium">{formatPrice(split.amount)}</span>
                                                            <button
                                                                onClick={() => setPaymentSplits(prev => prev.filter((_, i) => i !== idx))}
                                                                className="text-gray-400 hover:text-red-500"
                                                            >
                                                                <X className="w-4 h-4" />
                                                            </button>
                                                        </div>
                                                    </div>
                                                ))}
                                                <div className="flex justify-between pt-2 border-t border-gray-200 text-sm">
                                                    <span className="text-gray-600">Total pagado:</span>
                                                    <span className="font-bold text-success">
                                                        {formatPrice(paymentSplits.reduce((sum, s) => sum + s.amount, 0))}
                                                    </span>
                                                </div>
                                                <div className="flex justify-between text-sm">
                                                    <span className="text-gray-600">Restante:</span>
                                                    <span className={`font-bold ${
                                                        cartTotal - paymentSplits.reduce((sum, s) => sum + s.amount, 0) <= 0
                                                            ? 'text-success'
                                                            : 'text-amber-600'
                                                    }`}>
                                                        {formatPrice(Math.max(0, cartTotal - paymentSplits.reduce((sum, s) => sum + s.amount, 0)))}
                                                    </span>
                                                </div>
                                            </div>
                                        )}

                                        {/* Add payment split */}
                                        {paymentSplits.reduce((sum, s) => sum + s.amount, 0) < cartTotal && (
                                            <div className="mb-4 p-3 border border-dashed border-gray-300 rounded-xl">
                                                <p className="text-xs font-medium text-gray-500 mb-2">
                                                    {paymentSplits.length === 0 ? 'Método de pago:' : 'Agregar otro pago:'}
                                                </p>
                                                <div className="grid grid-cols-4 gap-2 mb-3">
                                                    {[
                                                        { method: 'cash' as const, label: 'Efectivo', icon: Banknote, color: 'green' },
                                                        { method: 'debit' as const, label: 'Débito', icon: CreditCard, color: 'blue' },
                                                        { method: 'credit' as const, label: 'Crédito', icon: CreditCard, color: 'purple' },
                                                        { method: 'transfer' as const, label: 'Transfer.', icon: ArrowLeftRight, color: 'cyan' },
                                                    ].map(({ method, label, icon: Icon, color }) => (
                                                        <button
                                                            key={method}
                                                            onClick={() => setPaymentMethod(method)}
                                                            className={`flex flex-col items-center gap-1 p-2 rounded-lg border-2 transition ${
                                                                paymentMethod === method
                                                                    ? `border-${color}-500 bg-${color}-50 text-${color}-700`
                                                                    : 'border-gray-200 hover:border-gray-300'
                                                            }`}
                                                        >
                                                            <Icon className="w-5 h-5" />
                                                            <span className="text-xs">{label}</span>
                                                        </button>
                                                    ))}
                                                </div>
                                                <div className="flex gap-2">
                                                    <input
                                                        type="number"
                                                        value={splitAmount}
                                                        onChange={(e) => setSplitAmount(e.target.value)}
                                                        placeholder={`${Math.max(0, cartTotal - paymentSplits.reduce((sum, s) => sum + s.amount, 0))}`}
                                                        className="flex-1 px-3 py-2 border border-gray-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-primary/20"
                                                    />
                                                    <button
                                                        onClick={() => {
                                                            const remaining = cartTotal - paymentSplits.reduce((sum, s) => sum + s.amount, 0);
                                                            const amount = parseFloat(splitAmount) || remaining;
                                                            if (amount > 0) {
                                                                setPaymentSplits(prev => [...prev, { method: paymentMethod, amount }]);
                                                                setSplitAmount('');
                                                            }
                                                        }}
                                                        className="px-4 py-2 bg-primary text-white rounded-lg hover:bg-primary/90 transition"
                                                    >
                                                        <Plus className="w-5 h-5" />
                                                    </button>
                                                </div>
                                            </div>
                                        )}
                                    </>
                                ) : (
                                    /* Single Payment UI */
                                    <>
                                        <div className="mb-6">
                                            <p className="text-sm font-medium text-gray-700 mb-2">Método de pago:</p>
                                            <div className="grid grid-cols-4 gap-3">
                                                {[
                                                    { method: 'cash', label: 'Efectivo', icon: Banknote, color: 'green' },
                                                    { method: 'debit', label: 'Débito', icon: CreditCard, color: 'blue' },
                                                    { method: 'credit', label: 'Crédito', icon: CreditCard, color: 'purple' },
                                                    { method: 'transfer', label: 'Transf.', icon: ArrowLeftRight, color: 'cyan' },
                                                ].map(({ method, label, icon: Icon, color }) => (
                                                    <button
                                                        key={method}
                                                        onClick={() => setPaymentMethod(method as any)}
                                                        className={`flex flex-col items-center justify-center p-3 rounded-xl border-2 transition ${
                                                            paymentMethod === method
                                                                ? `border-${color}-500 bg-${color}-50 text-${color}-700`
                                                                : 'border-gray-100 hover:border-gray-200 text-gray-600'
                                                        }`}
                                                    >
                                                        <Icon className="w-6 h-6 mb-1" />
                                                        <span className="text-xs font-medium">{label}</span>
                                                    </button>
                                                ))}
                                            </div>
                                        </div>

                                        {paymentMethod === 'cash' && (
                                            <div className="mb-4">
                                                <label className="block text-sm font-medium text-gray-700 mb-1">
                                                    Monto recibido:
                                                </label>
                                                <input
                                                    type="number"
                                                    value={paidAmount}
                                                    onChange={(e) => setPaidAmount(e.target.value)}
                                                    placeholder={cartTotal.toString()}
                                                    className="w-full px-4 py-3 border border-gray-200 rounded-xl text-lg focus:outline-none focus:ring-2 focus:ring-primary/20"
                                                    autoFocus
                                                />
                                                <div className="mt-2 flex justify-between">
                                                    <span className="text-sm text-gray-600">Vuelto:</span>
                                                    <span className={`font-bold ${changeAmount >= 0 ? 'text-success' : 'text-danger'}`}>
                                                        {formatPrice(changeAmount)}
                                                    </span>
                                                </div>
                                            </div>
                                        )}
                                    </>
                                )}

                                {/* Change display when overpaid with cash (shared for both modes) */}
                                {isSplitPayment && paymentSplits.some(s => s.method === 'cash') &&
                                 paymentSplits.reduce((sum, s) => sum + s.amount, 0) > cartTotal && (
                                    <div className="mb-4 p-3 bg-success/10 rounded-xl">
                                        <div className="flex justify-between">
                                            <span className="text-gray-700 font-medium">Vuelto:</span>
                                            <span className="text-success font-bold text-xl">
                                                {formatPrice(paymentSplits.reduce((sum, s) => sum + s.amount, 0) - cartTotal)}
                                            </span>
                                        </div>
                                    </div>
                                )}

                                <div className="flex gap-3">
                                    <button
                                        onClick={() => {
                                            setShowPaymentModal(false);
                                            setPaidAmount('');
                                            setPaymentSplits([]);
                                            setSplitAmount('');
                                            setIsSplitPayment(false);
                                        }}
                                        className="flex-1 py-3 border border-gray-200 text-gray-700 font-medium rounded-xl hover:bg-gray-50 transition"
                                    >
                                        Cancelar
                                    </button>
                                    <button
                                        onClick={processSale}
                                        disabled={
                                            processing ||
                                            (isSplitPayment
                                                ? paymentSplits.reduce((sum, s) => sum + s.amount, 0) < cartTotal
                                                : (paymentMethod === 'cash' && parseFloat(paidAmount || '0') < cartTotal && paidAmount !== '')
                                            )
                                        }
                                        className="flex-1 py-3 bg-success text-white font-semibold rounded-xl hover:bg-success/90 transition disabled:opacity-50 flex items-center justify-center gap-2"
                                    >
                                        Finalizar Venta
                                    </button>
                                </div>
                            </>
                        )}
                    </div>
                </div>
            )}

            {/* Ticket Drawer */}
            <Drawer
                open={showTicketDrawer}
                onClose={() => {
                    setShowTicketDrawer(false);
                    setTicketData(null);
                }}
                title="Ticket de Venta"
                size="lg"
            >
                {ticketData && (
                    <div className="flex flex-col h-full">
                        {/* Ticket Preview */}
                        <div className="flex-1 p-6 overflow-y-auto">
                            <div
                                ref={ticketRef}
                                className="bg-white border border-gray-200 rounded-lg p-4 text-xs"
                                style={{
                                    fontFamily: "'Roboto Mono', monospace",
                                    lineHeight: '1.5',
                                    color: '#000'
                                }}
                            >
                                {/* Store Header with Logo */}
                                <div className="text-center border-b border-solid border-gray-400 pb-3 mb-3">
                                    {storeSettings.logo && (
                                        <img
                                            src={storeSettings.logo}
                                            alt="Logo"
                                            className="w-10 h-10 mx-auto mb-1 object-contain"
                                        />
                                    )}
                                    <p className="font-bold text-base">{storeSettings.company_name || 'Mi Tienda'}</p>
                                    <p className="text-[10px] leading-tight mt-1">
                                        {storeSettings.company_name && <>{storeSettings.company_name}<br /></>}
                                        {storeSettings.company_rut && <>RUT: {storeSettings.company_rut}<br /></>}
                                        {storeSettings.company_address && <>{storeSettings.company_address}<br /></>}
                                        {storeSettings.company_phone && <>Teléfono: {storeSettings.company_phone}<br /></>}
                                        {storeSettings.company_email && <>{storeSettings.company_email}</>}
                                    </p>
                                </div>

                                {/* Sale Info */}
                                <div className="border-b border-solid border-gray-400 pb-3 mb-3 text-[10px]">
                                    <p><strong>Ticket N°:</strong> {ticketData.receipt_number}</p>
                                    <p><strong>Fecha:</strong> {ticketData.date} hrs</p>
                                    {ticketData.payments && ticketData.payments.length > 1 ? (
                                        <>
                                            <p><strong>Pagos:</strong></p>
                                            {ticketData.payments.map((p, i) => (
                                                <p key={i} className="ml-2">
                                                    - {p.method === 'cash' ? 'Efectivo' : p.method === 'debit' ? 'Débito' : p.method === 'credit' ? 'Crédito' : 'Transferencia'}: {formatPrice(p.amount)}
                                                </p>
                                            ))}
                                        </>
                                    ) : (
                                        <p><strong>Pago:</strong> {ticketData.payment_method === 'cash' ? 'Efectivo' : ticketData.payment_method === 'debit' ? 'Débito' : ticketData.payment_method === 'credit' ? 'Crédito' : 'Transferencia'}</p>
                                    )}
                                    <p><strong>Atendido por:</strong> Tiendas Listto 001</p>
                                </div>

                                {/* Items Table */}
                                <div className="border-b border-solid border-gray-400 pb-3 mb-3">
                                    <table className="w-full text-[11px]">
                                        <thead>
                                            <tr className="border-b border-gray-800">
                                                <th className="text-left py-1 font-bold" style={{ width: '60%' }}>Producto</th>
                                                <th className="text-right py-1 font-bold" style={{ width: '15%' }}>Cant.</th>
                                                <th className="text-right py-1 font-bold" style={{ width: '25%' }}>Total</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            {ticketData.items.map((item, idx) => (
                                                <tr key={idx}>
                                                    <td className="py-1">
                                                        <span className="font-bold text-xs">{item.name}</span>
                                                        <br />
                                                        <span className="text-[10px]">({item.quantity} x {formatPrice(item.unit_price)})</span>
                                                    </td>
                                                    <td className="text-right align-top py-1">{item.quantity}</td>
                                                    <td className="text-right align-top py-1">{formatPrice(item.subtotal)}</td>
                                                </tr>
                                            ))}
                                        </tbody>
                                    </table>
                                </div>

                                {/* Totals */}
                                <div className="space-y-1 text-sm mt-4">
                                    <div className="flex justify-between">
                                        <span className="text-gray-600">Subtotal:</span>
                                        <span className="font-medium">{formatPrice(ticketData.total / 1.19)}</span>
                                    </div>
                                    <div className="flex justify-between">
                                        <span className="text-gray-600">IVA (19%):</span>
                                        <span className="font-medium">{formatPrice(ticketData.total - ticketData.total / 1.19)}</span>
                                    </div>
                                    <div className="flex justify-between pt-2 border-t border-gray-200 mt-2">
                                        <span className="text-primary font-semibold">Total a Pagar:</span>
                                        <span className="text-primary font-bold text-lg">{formatPrice(ticketData.total)}</span>
                                    </div>
                                    {ticketData.change > 0 && (
                                        <div className="flex justify-between">
                                            <span className="text-success">Vuelto:</span>
                                            <span className="text-success font-bold">{formatPrice(ticketData.change)}</span>
                                        </div>
                                    )}
                                </div>

                                {/* QR Code */}
                                <div className="text-center mb-3">
                                    <p className="text-[10px] mb-1">Conoce todos nuestros productos en nuestra web</p>
                                    <img
                                        src={`https://api.qrserver.com/v1/create-qr-code/?size=112x112&data=${encodeURIComponent('https://tiendaslistto.cl/app/index.php')}`}
                                        alt="Código QR App Web"
                                        className="w-28 h-28 mx-auto"
                                    />
                                </div>

                                {/* Footer */}
                                <div className="text-center border-t border-solid border-gray-400 pt-3">
                                    <p className="font-bold text-xs">¡Gracias por tu compra!</p>
                                    <p className="text-[10px] mt-1">
                                        Visítanos en www.tiendaslistto.cl<br />
                                        Síguenos en Instagram: @listtocl
                                    </p>
                                    <p className="text-[9px] mt-2 leading-tight">
                                        Conserve su ticket para cambios o devoluciones.
                                    </p>
                                </div>
                            </div>
                        </div>

                        {/* Footer with Buttons */}
                        <div className="shrink-0 border-t border-gray-100 bg-white px-6 py-4 shadow-panel">
                            <div className="flex items-center justify-end gap-3">
                                <button
                                    type="button"
                                    onClick={() => {
                                        setShowTicketDrawer(false);
                                        setTicketData(null);
                                    }}
                                    className="px-5 py-2.5 cursor-pointer text-sm font-bold text-[#334155] hover:text-gray-800 hover:bg-gray-100 rounded-lg transition-colors"
                                >
                                    Cerrar
                                </button>
                                <button
                                    type="button"
                                    onClick={() => {
                                        const printWindow = window.open('', '', 'width=600,height=600');
                                        if (printWindow && ticketRef.current) {
                                            printWindow.document.write(`
                                                <html>
                                                <head>
                                                    <title>Ticket #${ticketData.receipt_number}</title>
                                                    <link href="https://fonts.googleapis.com/css2?family=Roboto+Mono:wght@400;700&display=swap" rel="stylesheet">
                                                    <style>
                                                        body {
                                                            font-family: 'Roboto Mono', monospace;
                                                            font-size: 11px;
                                                            width: 75mm;
                                                            margin: 0;
                                                            padding: 10px;
                                                            line-height: 1.5;
                                                            color: #000;
                                                        }
                                                        table { width: 100%; border-collapse: collapse; }
                                                        th, td { padding: 4px 0; }
                                                        img { display: block; margin: 0 auto; }
                                                        .text-center { text-align: center; }
                                                        .text-right { text-align: right; }
                                                        .font-bold { font-weight: bold; }
                                                        .border-b { border-bottom: 1px solid #000; padding-bottom: 8px; margin-bottom: 8px; }
                                                        .border-t { border-top: 2px solid #000; padding-top: 8px; }
                                                    </style>
                                                </head>
                                                <body onload="window.print(); window.close();">${ticketRef.current.innerHTML}</body>
                                                </html>
                                            `);
                                            printWindow.document.close();
                                        }
                                    }}
                                    className="px-5 py-2.5 cursor-pointer text-sm font-bold text-white bg-primary hover:bg-primary/90 rounded-lg transition-colors flex items-center gap-2"
                                >
                                    <Printer className="w-4 h-4" />
                                    Imprimir
                                </button>
                            </div>
                        </div>
                    </div>
                )}
            </Drawer>
            {/* Sales Return Modal */}
            <Dialog open={showReturnModal} onOpenChange={setShowReturnModal}>
                <DialogContent className="sm:max-w-md">
                    <DialogHeader>
                        <DialogTitle className="text-center flex items-center justify-center gap-2">
                            Anulación de Venta <RotateCcw className="w-4 h-4 text-blue-500" />
                        </DialogTitle>
                    </DialogHeader>

                    {!returnTicketData ? (
                        <form onSubmit={handleTicketLookup} className="space-y-4 py-4">
                            <div className="space-y-2">
                                <label className="text-sm font-medium text-center block text-gray-700">Ticket Nro:</label>
                                <input
                                    type="text" // using text to avoid spinners, but expected number
                                    value={returnTicketId}
                                    onChange={(e) => setReturnTicketId(e.target.value)}
                                    className="w-full text-center text-lg p-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-primary outline-none"
                                    placeholder="Ej: 6260"
                                    autoFocus
                                />
                            </div>
                            <div className="flex gap-2 justify-center">
                                <button
                                    type="submit"
                                    className="px-4 py-2 bg-primary text-white rounded-lg hover:bg-primary/90 font-medium"
                                >
                                    Buscar Ticket
                                </button>
                            </div>
                        </form>
                    ) : (
                        <div className="space-y-4">
                            <div className="bg-blue-50 p-3 rounded-lg text-center text-sm border border-blue-100">
                                <p className="font-bold text-gray-900">Ticket: {returnTicketData.receipt_number} ({returnTicketData.time})</p>
                                <p className="text-blue-600 font-bold mt-1">Total Original: {formatPrice(returnTicketData.total)}</p>
                            </div>

                            <p className="text-sm font-medium text-center">Seleccione Items a Devolver:</p>

                            <div className="max-h-[300px] overflow-y-auto space-y-2 border rounded-lg p-2">
                                {returnTicketData.items.map((item, idx) => {
                                    const returnableQty = getReturnableQuantity(returnTicketData, item.product_id, item.quantity);
                                    const isFullyReturned = returnableQty === 0;

                                    return (
                                        <div key={idx} className={`flex items-center justify-between p-2 rounded border text-sm ${isFullyReturned ? 'bg-gray-100 border-gray-200 opacity-75' : 'bg-gray-50 border-gray-100'}`}>
                                            <div className="flex-1">
                                                <p className="font-medium truncate">{item.name}</p>
                                                <p className="text-xs text-gray-500">{formatPrice(item.unit_price)} c/u</p>
                                            </div>
                                            <div className="flex items-center gap-3">
                                                <div className="text-center">
                                                    <span className="block text-xs text-gray-400">Vendidos</span>
                                                    <span className="font-bold">{item.quantity}</span>
                                                </div>
                                                <div className="text-center">
                                                    <span className="block text-xs text-gray-400">Disp.</span>
                                                    <span className={`font-bold ${isFullyReturned ? 'text-red-500' : 'text-green-600'}`}>
                                                        {returnableQty}
                                                    </span>
                                                </div>
                                                <div className="text-center">
                                                    <span className="block text-xs text-gray-400">Devolver</span>
                                                    <input
                                                        type="number"
                                                        min="0"
                                                        max={returnableQty}
                                                        disabled={isFullyReturned}
                                                        value={returnItems[item.name] || 0}
                                                        onChange={(e) => handleReturnItemChange(item.name, parseInt(e.target.value) || 0, returnableQty)}
                                                        className="w-16 p-1 text-center border rounded focus:ring-1 focus:ring-primary outline-none disabled:bg-gray-200"
                                                    />
                                                </div>
                                            </div>
                                        </div>
                                    );
                                })}
                            </div>

                            <div className="border-t pt-3 text-center">
                                <p className="text-sm text-gray-600">Monto Total a Devolver (Estimado):</p>
                                <p className="text-xl font-bold text-gray-900">{formatPrice(calculateRefundTotal())}</p>
                            </div>

                            <div className="bg-red-50 p-2 rounded text-xs text-red-600 text-center border border-red-100">
                                NOTA: Los ítems devueltos se eliminarán de la venta (o se reducirá su cantidad) y se repondrá el stock.
                            </div>

                            <div className="flex gap-2 pt-2">
                                <button
                                    onClick={() => setReturnTicketData(null)}
                                    className="flex-1 px-4 py-2 bg-gray-200 text-gray-700 rounded-lg hover:bg-gray-300 font-medium"
                                >
                                    Cancelar
                                </button>
                                <button
                                    onClick={confirmReturn}
                                    className="flex-1 px-4 py-2 bg-gray-500 text-white rounded-lg hover:bg-gray-600 font-medium"
                                >
                                    Finalizar Devolución
                                </button>
                            </div>
                        </div>
                    )}
                </DialogContent>
            </Dialog>
        </AuthenticatedLayout>
    );
}
