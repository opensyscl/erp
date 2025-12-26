// js/orders.js

// =========================================================
// FUNCIONES DE CÁLCULO (Costos de Compra: Neto <-> Bruto)
// =========================================================

/**
 * Calcula el Costo Bruto (con IVA) a partir del Costo Neto.
 */
function calculateGrossCost(netCost) {
    // ASUMO que IVA_RATE existe globalmente (inyectado desde PHP)
    return netCost * (1 + IVA_RATE);
}

/**
 * Calcula el Costo Neto (sin IVA) a partir del Costo Bruto.
 */
function calculateNetCost(grossCost) {
    const divisor = 1 + IVA_RATE;
    if (divisor === 0) return 0;
    return grossCost / divisor;
}

/**
 * Formatea un número como moneda CLP.
 */
function formatCurrency(amount) {
    // Aseguramos que sea un entero para CLP
    return currencyFormatter.format(Math.round(amount));
}

/**
 * Limpia un string de moneda de caracteres no numéricos ($.).
 */
function cleanCurrencyString(currencyString) {
    // Limpia $, puntos (separadores de miles) y espacios
    return currencyString.replace(/[$.]/g, '').trim();
}


// =========================================================
// REFERENCIAS Y ESTADO GLOBAL
// =========================================================

const invoiceItemsBody = document.getElementById('invoice-items-body');
const subtotalNetDisplay = document.getElementById('subtotal-net');
const ivaAmountDisplay = document.getElementById('iva-amount');
const totalGrossDisplay = document.getElementById('total-gross');
const registerOrderBtn = document.getElementById('register-invoice-btn');
const noProductsRow = document.getElementById('no-products-row');
const clearOrderBtn = document.getElementById('clear-invoice-btn');
const ordersListBody = document.getElementById('orders-list-body');


// Modales y Controles
const addProductBtn = document.getElementById('add-product-btn');
const addNewProductBtn = document.getElementById('add-new-product-btn');
const selectProductModal = document.getElementById('select-product-modal');
const addNewProductModal = document.getElementById('add-new-product-modal');
const newProductForm = document.getElementById('new-product-form');
const productGridContainer = document.getElementById('product-grid-container');

const closeModalButtons = document.querySelectorAll('.close-button');
const registerModal = document.getElementById('register-modal');
const statusMessage = document.getElementById('status-message');
const finalOrderForm = document.getElementById('final-invoice-form');
const modalTotalDisplay = document.getElementById('modal-total-display');


// =========================================================
// FUNCIONES DE CONTROL DE MODALES Y MENSAJES
// =========================================================

/**
 * Abre un modal y maneja el estado de accesibilidad.
 * @param {HTMLElement} modalElement
 */
function openModal(modalElement) {
    if (modalElement) {
        modalElement.classList.add('is-active');
        modalElement.style.display = 'flex'; // Usamos flex para centrar
        modalElement.setAttribute('aria-hidden', 'false');
    }
}

/**
 * Cierra un modal y maneja el estado de accesibilidad.
 * @param {HTMLElement} modalElement
 */
function closeModal(modalElement) {
    if (modalElement) {
        modalElement.classList.remove('is-active');
        modalElement.style.display = 'none'; // Ocultar
        modalElement.setAttribute('aria-hidden', 'true');
    }
}

function showStatusMessage(message, type = 'info', duration = 3000) {
    // Si la referencia global no se encontró al cargar, salimos.
    if (!statusMessage) return; 

    // Limpiar clases previas
    statusMessage.classList.remove('is-active', 'success', 'warning', 'error', 'info');
    statusMessage.innerHTML = message;
    statusMessage.classList.add('is-active', type);

    // Mantenemos la propiedad display para compatibilidad con el CSS inicial
    statusMessage.style.display = 'block';

    setTimeout(() => {
        statusMessage.classList.remove('is-active');
        statusMessage.style.display = 'none';
    }, duration);
}


// =========================================================
// LÓGICA DE LA TABLA Y CÁLCULOS
// =========================================================

/**
 * Recalcula todos los totales de la Orden de Compra (Neto, IVA, Bruto).
 */
function updateInvoiceTotals() {
    let subtotalNet = 0;
    let subtotalGross = 0;

    const itemsWithValidData = INVOICE_ITEMS.filter(item => {
        // Validación simplificada: debe tener un costo neto y cantidad válida
        return (item.cost_price_net_new !== undefined && item.quantity > 0);
    });

    itemsWithValidData.forEach(item => {
        const netCost = Math.round(parseFloat(item.cost_price_net_new || 0));
        const grossCost = calculateGrossCost(netCost);
        const quantity = parseInt(item.quantity || 0);

        subtotalNet += netCost * quantity;
        subtotalGross += grossCost * quantity;
    });

    const totalGross = Math.round(subtotalGross);
    const ivaAmount = totalGross - Math.round(subtotalNet);

    subtotalNetDisplay.textContent = formatCurrency(Math.round(subtotalNet));
    ivaAmountDisplay.textContent = formatCurrency(ivaAmount);
    totalGrossDisplay.textContent = formatCurrency(totalGross);

    // Habilitar/Deshabilitar el botón de registro
    registerOrderBtn.disabled = INVOICE_ITEMS.length === 0 || totalGross <= 0;

    if (modalTotalDisplay) {
        modalTotalDisplay.textContent = formatCurrency(totalGross);
    }
}

/**
 * Recalcula Costo Bruto o Costo Neto para una fila de pedido.
 */
function updateRowCalculations(row, index) {
    const item = INVOICE_ITEMS[index];

    const inputNetCost = row.querySelector('.input-costo-neto-pedido');
    const inputGrossCost = row.querySelector('.input-costo-bruto-pedido');
    const inputQuantity = row.querySelector('.input-quantity');

    let netCost = parseFloat(inputNetCost?.value || 0) || 0;
    let grossCost = parseFloat(inputGrossCost?.value || 0) || 0;
    let quantity = parseInt(inputQuantity?.value || 0) || 0;

    if (quantity < 1) {
        quantity = 1;
        inputQuantity.value = 1;
    }

    const activeElement = document.activeElement;

    // LÓGICA DE ACTUALIZACIÓN INVERSA (Neto <-> Bruto)
    if (activeElement === inputGrossCost) {
        // Se modificó el Bruto
        grossCost = Math.round(grossCost);
        netCost = calculateNetCost(grossCost);
        inputNetCost.value = Math.round(netCost);
    } else if (activeElement === inputNetCost) {
        // Se modificó el Neto
        netCost = Math.round(netCost);
        grossCost = calculateGrossCost(netCost);
        inputGrossCost.value = Math.round(grossCost);
    } else {
        // Se modificó la Cantidad o se llamó sin foco
        netCost = Math.round(parseFloat(inputNetCost.value));
        grossCost = Math.round(calculateGrossCost(netCost)); // Aseguramos que el bruto sea coherente
        inputGrossCost.value = grossCost; // Refrescar el input del bruto
    }


    // ACTUALIZAR ARREGLO GLOBAL
    item.cost_price_net_new = netCost;
    item.cost_price_gross_new = grossCost;
    item.quantity = quantity;

    // Recalcular Totales
    updateInvoiceTotals();
}

/**
 * Añade listeners a los inputs de una nueva fila para recalcular
 */
function addRowListeners(row, index) {
    const inputs = row.querySelectorAll('.input-costo-neto-pedido, .input-costo-bruto-pedido, .input-quantity');
    inputs.forEach(input => {
        // Usamos 'input' para cálculos en tiempo real y 'change' para asegurar la persistencia
        input.addEventListener('input', () => {
            updateRowCalculations(row, index);
        });
        input.addEventListener('change', () => {
            updateRowCalculations(row, index);
        });
    });
}


/**
 * Renderiza todos los ítems de la orden.
 */
function renderInvoiceItems() {
    invoiceItemsBody.innerHTML = '';

    if (INVOICE_ITEMS.length === 0) {
        // Restaurar la fila de "No hay productos" si existe
        const newNoProductsRow = document.createElement('tr');
        newNoProductsRow.id = 'no-products-row';
        newNoProductsRow.innerHTML = '<td colspan="8" style="text-align: center; padding: 1rem; color: #666;">Para empezar, agrega un prodcuto nuevo o existente</td>';
        invoiceItemsBody.appendChild(newNoProductsRow);
        updateInvoiceTotals();
        return;
    }

    INVOICE_ITEMS.forEach((product, index) => {
        const row = document.createElement('tr');
        row.dataset.productId = product.id.toString();

        // Si los costos de pedido no han sido inicializados, usamos los costos actuales como base
        const netCostNew = product.cost_price_net_new !== undefined ? product.cost_price_net_new : product.cost_price_net;
        const grossCostNew = product.cost_price_gross_new !== undefined ? product.cost_price_gross_new : product.cost_price_gross;


        // ESTRUCTURA DE LA FILA (8 COLUMNAS)
        row.innerHTML = `
            <td class="col-code">${product.code}</td>
            <td class="col-product">${product.name}</td>

            <td class="text-right">${formatCurrency(product.cost_price_net)}</td>
            <td class="text-right">${formatCurrency(product.cost_price_gross)}</td>

            <td>
                <input type="number" class="invoice-input input-costo-neto-pedido"
                        value="${Math.round(netCostNew)}" step="1" min="0" required>
            </td>
            <td>
                <input type="number" class="invoice-input input-costo-bruto-pedido"
                        value="${Math.round(grossCostNew)}" step="1" min="0" required>
            </td>
            <td>
                <input type="number" class="invoice-input input-quantity"
                        value="${product.quantity}" step="1" min="1" required>
            </td>
            
            <td>
                <button type="button" class="btn-remove btn-danger" data-id="${product.id}">ELIMINAR</button>
            </td>
        `;

        invoiceItemsBody.appendChild(row);
        addRowListeners(row, index);
    });

    // Añadir listener para la eliminación de filas
    invoiceItemsBody.querySelectorAll('.btn-remove').forEach(btn => {
        btn.addEventListener('click', (e) => {
            removeInvoiceItem(e.currentTarget.dataset.id);
        });
    });

    updateInvoiceTotals();
}


/**
 * Añade un producto al arreglo INVOICE_ITEMS y renderiza.
 * @param {object} product - El objeto producto (de ALL_SUPPLIER_PRODUCTS) o un nuevo producto temporal.
 */
function addItemToInvoice(product) {
    const numericId = parseInt(product.id);

    // 1. Verificar si el producto ya está en la orden
    const existingItemIndex = INVOICE_ITEMS.findIndex(item => parseInt(item.id) === numericId);

    if (existingItemIndex !== -1) {
        // Si ya existe, actualizamos cantidad
        const existingItem = INVOICE_ITEMS[existingItemIndex];
        const quantityToAdd = product.quantity || 1;
        existingItem.quantity = (existingItem.quantity || 0) + quantityToAdd;
        showStatusMessage(`Producto "${existingItem.name}" actualizado. Cantidad: ${existingItem.quantity}`, 'warning', 2000);
    } else {
        // 2. Agregar nuevo ítem
        const initialNetCost = Math.round(product.cost_price_net);
        const initialGrossCost = Math.round(product.cost_price_gross || calculateGrossCost(initialNetCost));

        const newItem = {
            id: numericId,
            code: product.code,
            name: product.name,
            cost_price_net: initialNetCost,
            cost_price_gross: initialGrossCost,
            quantity: product.quantity || 1, // Cantidad a agregar
            // Costos específicos del pedido (inicialmente iguales a los actuales)
            cost_price_net_new: initialNetCost,
            cost_price_gross_new: initialGrossCost,
        };
        INVOICE_ITEMS.push(newItem);
        showStatusMessage(`Producto "${product.name}" agregado a la orden.`, 'success', 2000);
    }

    // 3. Actualizar la interfaz
    renderInvoiceItems();
    // ✅ CLAVE: Se elimina la línea closeModal(selectProductModal); para mantener el modal abierto.
    renderProductGrid(); // Se re-renderiza para mostrar el estado "Agregado"
}


/**
 * Elimina un producto de la tabla de la orden y del arreglo global.
 */
function removeInvoiceItem(productId) {
    const numericId = parseInt(productId);

    const initialLength = INVOICE_ITEMS.length;

    // Filtra para crear un nuevo array sin el ítem a remover
    const newItems = INVOICE_ITEMS.filter(item => parseInt(item.id) !== numericId);

    if (newItems.length === initialLength) {
        showStatusMessage('Error: Producto no encontrado en la orden.', 'error');
        return;
    }

    // Vacía y rellena el array global para mantener la referencia (si es necesario)
    INVOICE_ITEMS.length = 0;
    INVOICE_ITEMS.push(...newItems);

    renderInvoiceItems();
    showStatusMessage('Producto eliminado de la orden.', 'warning', 2000);
    renderProductGrid(); // Re-renderizar la grilla para actualizar el estado visual
}


/**
 * Limpia la tabla de ítems de la orden y resetea los totales.
 */
function clearInvoice(suppressMessage = false) {
    INVOICE_ITEMS.length = 0;
    renderInvoiceItems();

    if (!suppressMessage) {
        showStatusMessage('La orden de compra ha sido limpiada.', 'info');
    }
    renderProductGrid();
}


// =========================================================
// LÓGICA DE MODALES (Grilla y Nuevo Producto)
// =========================================================

/**
 * Renderiza la grilla de productos en el modal de selección.
 */
function renderProductGrid() {
    if (!productGridContainer) return;

    // 1. Determinar qué productos ya están en la orden para deshabilitarlos
    const addedProductIds = INVOICE_ITEMS.map(item => parseInt(item.id));

    productGridContainer.innerHTML = ''; // Limpiar el contenedor

    if (ALL_SUPPLIER_PRODUCTS.length === 0) {
        productGridContainer.innerHTML = '<p class="search-info" style="grid-column: 1 / -1;">No hay productos registrados para este proveedor.</p>';
        return;
    }

    ALL_SUPPLIER_PRODUCTS.forEach(product => {
        const numericId = parseInt(product.id);
        const isNewProduct = numericId < 0; // ID temporal negativo

        const isAdded = addedProductIds.includes(numericId);
        // La tarjeta se ve deshabilitada si ya está agregada
        const isDisabledVisual = isAdded;

        const productCard = document.createElement('div');
        productCard.classList.add('product-card');
        productCard.dataset.productId = numericId; // Añadir el ID para el click

        if (isDisabledVisual) {
            productCard.classList.add('is-disabled');
        }

        // El listener se añade SIEMPRE
        productCard.addEventListener('click', () => {
            // Se envía el objeto completo que contiene code, name, costos actuales, etc.
            addItemToInvoice(product);
        });

        // Usar la URL de la imagen
        const imageUrl = product.image_url && product.image_url.trim() !== ''
            ? product.image_url
            : '/erp/img/default-product.png';

        // ESTRUCTURA DE LA TARJETA (Añadido: Stock, Costo)
        productCard.innerHTML = `
            <div class="product-image-wrapper">
                <img src="${imageUrl}" alt="${product.name}" class="product-image" onerror="this.onerror=null;this.src='/erp/img/default-product.png';">
                <div class="product-status-overlay">
                    ${isAdded ? '<span class="status-badge added"><i class="ph ph-check"></i> Agregado</span>' : ''}
                    ${product.stock <= 0 ? '<span class="status-badge no-stock"><i class="ph ph-warning"></i> Sin Stock</span>' : ''}
                    ${isNewProduct ? '<span class="status-badge new"><i class="ph ph-magic-wand"></i> NUEVO</span>' : ''}
                </div>
            </div>
            <div class="product-details">
                <h4 class="product-card-name">${product.name}</h4>
                <p class="product-card-info">
                    <span class="stock-info">Stock: <strong>${product.stock}</strong></span> |
                    <span class="code-info">C&oacute;d: ${product.code}</span>
                </p>
                <p class="product-card-price">Costo Act. Neto: <strong>${formatCurrency(product.cost_price_net)}</strong></p>
            </div>
        `;

        productGridContainer.appendChild(productCard);
    });
}


/**
 * Maneja la creación de un nuevo producto (simulación) y lo agrega a la orden.
 */
function handleNewProductSubmission(e) {
    e.preventDefault();

    const code = document.getElementById('new_product_code').value.trim();
    const name = document.getElementById('new_product_name').value.trim();
    const quantity = parseInt(document.getElementById('new_product_quantity')?.value) || 0;
    const costNet = parseFloat(document.getElementById('new_product_cost_net').value) || 0;

    if (!code || !name || costNet <= 0 || quantity <= 0) {
        showStatusMessage('Por favor, rellene todos los campos (código, nombre, cantidad, costo neto) y asegúrese de que los valores sean positivos.', 'error');
        return;
    }

    // Usaremos un ID temporal negativo y entero.
    const tempId = -1 * Math.floor(Date.now() / 1000);

    const newProduct = {
        id: tempId, // ID temporal como número entero (negativo)
        code: code,
        name: name,
        stock: 0,
        cost_price_net: Math.round(costNet),
        cost_price_gross: Math.round(calculateGrossCost(costNet)),
        image_url: '/erp/img/new-product-placeholder.png',
        current_sale_price: Math.round(calculateGrossCost(costNet) * 2),
        quantity: quantity
    };

    // Añadir al array de productos disponibles
    ALL_SUPPLIER_PRODUCTS.push(newProduct);
    addItemToInvoice(newProduct);

    newProductForm.reset();
    closeModal(addNewProductModal);
    renderProductGrid();
}


// =========================================================
// LÓGICA FINAL DE ENVÍO (AJAX)
// =========================================================

if (finalOrderForm) {
    finalOrderForm.addEventListener('submit', async (e) => {
        e.preventDefault();

        if (INVOICE_ITEMS.length === 0) {
            showStatusMessage('Debe agregar al menos un producto a la orden.', 'error');
            return;
        }

        const orderDate = document.getElementById('order_date')?.value;
        const supplierId = document.getElementById('final-supplier-id')?.value;

        if (orderDate === '' || !supplierId) {
            showStatusMessage('Faltan datos esenciales (Fecha de Orden o Proveedor).', 'error');
            return;
        }

        const submitButton = finalOrderForm.querySelector('button[type="submit"]');
        if (submitButton) {
            submitButton.disabled = true;
            submitButton.innerHTML = '<i class="ph ph-circle-notch spinner"></i> Procesando...';
        }

        const totalGrossText = totalGrossDisplay.textContent;
        const cleanedTotalGross = cleanCurrencyString(totalGrossText);
        const totalGross = parseFloat(cleanedTotalGross) || 0;

        const dataToSend = {
            supplier_id: parseInt(supplierId),
            order_date: orderDate,
            total_amount: Math.round(totalGross),
            items: INVOICE_ITEMS.map(item => ({
                product_id: parseInt(item.id),
                quantity: item.quantity,
                cost_price_net: Math.round(item.cost_price_net_new),
                product_code: item.code,
                product_name: item.name
            }))
        };

        try {
            const response = await fetch('register_order.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify(dataToSend)
            });

            const responseText = await response.text();

            if (!response.ok) {
                console.error('Error de servidor (HTTP no OK):', response.status, responseText);
                throw new Error(`Error en el servidor: Cód ${response.status}.`);
            }

            let result;
            try {
                result = JSON.parse(responseText);
            } catch (jsonError) {
                console.error('Error al parsear JSON:', jsonError);
                throw new Error(`La respuesta no fue JSON válido. Contenido:\n${responseText.substring(0, 150)}...`);
            }

            if (result.success) {
                const orderId = result.order_id;
                const orderNumber = result.order_number;
                const baseMessage = `Orden Nº${orderNumber} ha sido registrada con éxito.`;

                const viewButtonHtml = `
                    <a href="ver_oc.php?id=${orderId}" target="_blank" class="status-button">
                        <i class="ph ph-eye"></i> Visualizar Orden
                    </a>
                `;

                showStatusMessage(baseMessage + viewButtonHtml, 'success', 7000);

                // Limpieza y cierre
                clearInvoice(true);
                closeModal(registerModal);
                // Añadir a la lista en vivo (sin recargar la página)
                addNewOrderToLiveList(result);

            } else {
                showStatusMessage(result.message || 'Error desconocido al registrar la orden.', 'error', 7000);
                closeModal(registerModal);
            }

        } catch (error) {
            console.error('Fallo grave en la transacción:', error);
            showStatusMessage(`Fallo al generar la orden: ${error.message.substring(0, 100)}`, 'error', 7000);

            closeModal(registerModal);

        } finally {
            if (submitButton) {
                submitButton.disabled = false;
                submitButton.innerHTML = '<i class="ph ph-floppy-disk"></i> Generar y Guardar Orden';
            }
        }
    });
}


// =========================================================
// LÓGICA PARA ACTUALIZAR LISTA PRINCIPAL EN VIVO
// =========================================================

function addNewOrderToLiveList(orderData) {
    const ordersListBody = document.getElementById('orders-list-body');
    if (!ordersListBody) {
        console.warn('Advertencia: No se encontró el elemento #orders-list-body.');
        return;
    }

    const row = document.createElement('tr');
    row.classList.add('new-highlight');
    row.id = `order-row-${orderData.order_id}`;

    const orderNumber = orderData.order_number;
    const orderId = orderData.order_id;
    // Asume que el backend devuelve estos campos ya formateados o usa valores por defecto
    const orderDateDisplay = orderData.order_date_formatted || new Date().toLocaleDateString('es-CL');

    const supplierName = orderData.supplier_name || 'Desconocido';

    const totalAmount = orderData.total_amount || 0;
    const totalAmountDisplay = (totalAmount > 0) ? formatCurrency(totalAmount) : 'N/A';

    const creatorUsername = orderData.creator_username || 'Usuario Actual';

    // ESTRUCTURA FINAL DE 6 COLUMNAS
    row.innerHTML = `
        <td>${orderNumber}</td>
        <td>${supplierName}</td>
        <td>${orderDateDisplay}</td>
        <td>${totalAmountDisplay}</td>
        <td>${creatorUsername}</td>
        <td>
            <a href="ver_oc.php?id=${orderId}" target="_blank" class="btn-view-invoice">
                <i class="ph ph-magnifying-glass"></i> Ver Detalle
            </a>
        </td>
    `;

    // Eliminar la fila de "no hay órdenes" si existe
    const noOrdersRow = ordersListBody.querySelector('tr td[colspan="6"]');
    if (noOrdersRow) {
        const parentRow = noOrdersRow.closest('tr');
        if(parentRow) parentRow.remove();
    }

    // Insertar la nueva orden al principio
    ordersListBody.prepend(row);

    // Efecto visual de resaltado temporal
    setTimeout(() => {
        row.classList.remove('new-highlight');
    }, 5000);
}


// =========================================================
// INICIALIZACIÓN DE EVENTOS
// =========================================================

document.addEventListener('DOMContentLoaded', () => {

    // --- Inicialización del display de costos en el modal de nuevo producto ---
    const newProductCostNet = document.getElementById('new_product_cost_net');
    if (newProductCostNet) {
        const updateNewProductPrice = () => {
            const netCost = parseFloat(newProductCostNet.value) || 0;
            const grossCost = calculateGrossCost(netCost);
            const display = document.getElementById('new_product_cost_gross_display');
            if (display) {
                display.textContent = formatCurrency(grossCost);
            }
        };
        newProductCostNet.addEventListener('input', updateNewProductPrice);
        updateNewProductPrice();
    }

    // 1. Eventos de Control de Modales (Apertura)
    if (addProductBtn && selectProductModal) {
        // Adjuntar el listener al botón para abrir el modal
        addProductBtn.addEventListener('click', () => {
            renderProductGrid(); // Renderiza la grilla cada vez que se abre
            openModal(selectProductModal);
        });
    }

    if (addNewProductBtn && addNewProductModal) {
        addNewProductBtn.addEventListener('click', () => {
            newProductForm.reset();
            if(newProductCostNet) newProductCostNet.dispatchEvent(new Event('input'));
            openModal(addNewProductModal);
            document.getElementById('new_product_code').focus();
        });
    }

    if (newProductForm) {
        newProductForm.addEventListener('submit', handleNewProductSubmission);
    }


    // 2. Eventos de Control de Modales (Cierre)
    closeModalButtons.forEach(button => {
        button.addEventListener('click', () => {
            const modalId = button.getAttribute('data-modal-target');
            closeModal(document.getElementById(modalId));
        });
    });

    // Cierre al hacer clic fuera del modal (backdrop)
    document.querySelectorAll('.modal').forEach(modal => {
        modal.addEventListener('click', (e) => {
            // Si el clic fue directamente en el contenedor del modal (el fondo oscuro)
            if (e.target === modal) {
                closeModal(modal);
            }
        });
    });

    // 3. Evento para el botón limpiar pedido
    if (clearOrderBtn) {
        clearOrderBtn.addEventListener('click', () => {
            if (INVOICE_ITEMS.length > 0 && confirm('¿Estás seguro de que quieres limpiar toda la orden de compra?')) {
                clearInvoice(false);
            } else if (INVOICE_ITEMS.length === 0) {
                showStatusMessage('La orden ya está vacía.', 'info');
            }
        });
    }

    // 4. Apertura del modal de registro final
    if (registerOrderBtn && registerModal) {
        registerOrderBtn.addEventListener('click', (e) => {
            e.preventDefault();
            if (INVOICE_ITEMS.length > 0) {
                updateInvoiceTotals();
                openModal(registerModal);
                document.getElementById('order_date').focus();
            } else {
                showStatusMessage('Agregue productos antes de registrar la orden.', 'error');
            }
        });
    }

    // Inicializar la tabla y los totales al cargar
    renderInvoiceItems();
});