// js/quotation.js - Adaptado para Generación de Cotizaciones

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
// REFERENCIAS Y ESTADO GLOBAL (Nomenclatura ajustada a "Quotation")
// =========================================================

const invoiceItemsBody = document.getElementById('invoice-items-body');
const subtotalNetDisplay = document.getElementById('subtotal-net');
const ivaAmountDisplay = document.getElementById('iva-amount');
const totalGrossDisplay = document.getElementById('total-gross');
// RENOMBRADO: registerOrderBtn -> registerQuotationBtn
const registerQuotationBtn = document.getElementById('register-invoice-btn'); 
const noProductsRow = document.getElementById('no-products-row');
// RENOMBRADO: clearOrderBtn -> clearQuotationBtn
const clearQuotationBtn = document.getElementById('clear-invoice-btn'); 
// RENOMBRADO: ordersListBody -> quotationsListBody
const quotationsListBody = document.getElementById('orders-list-body'); 


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
// RENOMBRADO: finalOrderForm -> finalQuotationForm
const finalQuotationForm = document.getElementById('final-invoice-form'); 
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
 * Recalcula todos los totales de la Cotización (Neto, IVA, Bruto).
 */
function updateInvoiceTotals() {
    let subtotalNet = 0;
    let subtotalGross = 0;

    const itemsWithValidData = INVOICE_ITEMS.filter(item => {
        // Validación simplificada: debe tener un costo neto y cantidad válida
        return (item.cost_price_net_new !== undefined && item.quantity > 0);
    });

    itemsWithValidData.forEach(item => {
        // Usamos Math.round en la conversión de float a int para evitar errores de coma flotante
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
    registerQuotationBtn.disabled = INVOICE_ITEMS.length === 0 || totalGross <= 0;

    if (modalTotalDisplay) {
        modalTotalDisplay.textContent = formatCurrency(totalGross);
    }
}

/**
 * Recalcula Costo Bruto o Costo Neto para una fila de cotización.
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
 * Renderiza todos los ítems de la cotización.
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
        // Usamos el ID original, que será negativo para productos temporales o positivo para existentes.
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

    // 1. Verificar si el producto ya está en la cotización
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
            // Costos específicos de la cotización (inicialmente iguales a los actuales)
            cost_price_net_new: initialNetCost,
            cost_price_gross_new: initialGrossCost,
        };
        INVOICE_ITEMS.push(newItem);
        showStatusMessage(`Producto "${product.name}" agregado a la cotizaci&oacute;n.`, 'success', 2000); // Texto ajustado
    }

    // 3. Actualizar la interfaz
    renderInvoiceItems();
    renderProductGrid(); // Se re-renderiza para mostrar el estado "Agregado"
}


/**
 * Elimina un producto de la tabla de la cotización y del arreglo global.
 */
function removeInvoiceItem(productId) {
    const numericId = parseInt(productId);

    const initialLength = INVOICE_ITEMS.length;

    // Filtra para crear un nuevo array sin el ítem a remover
    const newItems = INVOICE_ITEMS.filter(item => parseInt(item.id) !== numericId);

    if (newItems.length === initialLength) {
        showStatusMessage('Error: Producto no encontrado en la cotización.', 'error');
        return;
    }

    // Vacía y rellena el array global para mantener la referencia (si es necesario)
    INVOICE_ITEMS.length = 0;
    INVOICE_ITEMS.push(...newItems);

    renderInvoiceItems();
    showStatusMessage('Producto eliminado de la cotizaci&oacute;n.', 'warning', 2000); // Texto ajustado
    renderProductGrid(); // Re-renderizar la grilla para actualizar el estado visual
}


/**
 * Limpia la tabla de ítems de la cotización y resetea los totales.
 */
function clearInvoice(suppressMessage = false) {
    INVOICE_ITEMS.length = 0;
    renderInvoiceItems();

    if (!suppressMessage) {
        showStatusMessage('La cotización ha sido limpiada.', 'info'); // Texto ajustado
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

    // 1. Determinar qué productos ya están en la cotización para deshabilitarlos
    const addedProductIds = INVOICE_ITEMS.map(item => parseInt(item.id));

    productGridContainer.innerHTML = ''; // Limpiar el contenedor

    if (ALL_SUPPLIER_PRODUCTS.length === 0) {
        productGridContainer.innerHTML = '<p class="search-info" style="grid-column: 1 / -1;">No hay productos registrados para este proveedor.</p>';
        return;
    }

    ALL_SUPPLIER_PRODUCTS.forEach(product => {
        const numericId = parseInt(product.id);
        // Productos temporales (los que no existen en la DB) tienen ID negativo
        const isNewProduct = numericId < 0; 

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
                    ${isNewProduct ? '<span class="status-badge new"><i class="ph ph-magic-wand"></i> NUEVO (Temp)</span>' : ''}
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
 * Maneja la creación de un nuevo producto (simulación) y lo agrega a la cotización.
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

    // Usaremos un ID temporal negativo y entero. CLAVE: Esto asegura que el producto no tiene ID real.
    const tempId = -1 * Math.floor(Date.now() / 1000); 

    const newProduct = {
        id: tempId, // ID temporal como número entero (negativo)
        code: code,
        name: name,
        stock: 0, // Stock es 0 ya que no existe en la DB
        cost_price_net: Math.round(costNet),
        cost_price_gross: Math.round(calculateGrossCost(costNet)),
        image_url: '/erp/img/new-product-placeholder.png',
        current_sale_price: Math.round(calculateGrossCost(costNet) * 2), // Solo un placeholder
        quantity: quantity
    };

    // Añadir al array de productos disponibles para que aparezca en el modal de selección
    ALL_SUPPLIER_PRODUCTS.push(newProduct); 
    addItemToInvoice(newProduct);

    newProductForm.reset();
    closeModal(addNewProductModal);
    renderProductGrid();
}


// =========================================================
// LÓGICA FINAL DE ENVÍO (AJAX) - AJUSTADO AL ENDPOINT DE COTIZACIONES
// =========================================================

if (finalQuotationForm) { // Usamos la nueva referencia
    finalQuotationForm.addEventListener('submit', async (e) => {
        e.preventDefault();

        if (INVOICE_ITEMS.length === 0) {
            showStatusMessage('Debe agregar al menos un producto a la cotizaci&oacute;.', 'error');
            return;
        }

        const orderDate = document.getElementById('order_date')?.value;
        const supplierId = document.getElementById('final-supplier-id')?.value;

        if (orderDate === '' || !supplierId) {
            showStatusMessage('Faltan datos esenciales (Fecha de Emisi&oacute;n o Proveedor).', 'error');
            return;
        }

        const submitButton = finalQuotationForm.querySelector('button[type="submit"]');
        if (submitButton) {
            submitButton.disabled = true;
            submitButton.innerHTML = '<i class="ph ph-circle-notch spinner"></i> Generando cotizaci&oacute;...';
        }

        const totalGrossText = totalGrossDisplay.textContent;
        const cleanedTotalGross = cleanCurrencyString(totalGrossText);
        const totalGross = parseFloat(cleanedTotalGross) || 0;

        const dataToSend = {
            supplier_id: parseInt(supplierId),
            order_date: orderDate,
            total_amount: Math.round(totalGross),
            items: INVOICE_ITEMS.map(item => ({
                // Si el ID es negativo (producto temporal), el backend lo tratará como NULL
                product_id: parseInt(item.id) > 0 ? parseInt(item.id) : null, 
                quantity: item.quantity,
                // CLAVE: Renombrados a cost_net y cost_gross para que coincidan con la tabla quotation_items
                cost_net: Math.round(item.cost_price_net_new), 
                cost_gross: Math.round(calculateGrossCost(item.cost_price_net_new)), 
                code: item.code,
                name: item.name
            }))
        };

        try {
            // CLAVE: Se cambia la URL al nuevo endpoint
            const response = await fetch('save_quotation.php', { 
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
                // CLAVE: Cambiadas las referencias a cotización
                const quotationId = result.quotation_id;
                const quotationNumber = result.quotation_number;
                const baseMessage = `Cotizaci&oacute;n Nro ${quotationNumber} ha sido registrada correctamente.`; 

                const viewButtonHtml = `
                    <a href="ver_cotizacion.php?id=${quotationId}" target="_blank" class="status-button">
                        <i class="ph ph-eye"></i> Visualizar Cotizaci&oacute;n
                    </a>
                `;

                showStatusMessage(baseMessage + viewButtonHtml, 'success', 7000);

                // Limpieza y cierre
                clearInvoice(true);
                closeModal(registerModal);
                // Añadir a la lista en vivo (sin recargar la página)
                addNewQuotationToLiveList(result); // Usamos nueva función

            } else {
                showStatusMessage(result.message || 'Error desconocido al registrar la cotizaci&oacute;.', 'error', 7000);
                closeModal(registerModal);
            }

        } catch (error) {
            console.error('Fallo grave en la transacción:', error);
            showStatusMessage(`Fallo al generar la cotización: ${error.message.substring(0, 100)}`, 'error', 7000);

            closeModal(registerModal);

        } finally {
            if (submitButton) {
                submitButton.disabled = false;
                submitButton.innerHTML = '<i class="ph ph-floppy-disk"></i> Generar y Guardar Cotizaci&oacute;';
            }
        }
    });
}


// =========================================================
// LÓGICA PARA ACTUALIZAR LISTA PRINCIPAL EN VIVO
// =========================================================

function addNewQuotationToLiveList(quotationData) { // Función renombrada
    if (!quotationsListBody) { // Referencia ajustada
        console.warn('Advertencia: No se encontró el elemento #orders-list-body (ahora lista de cotizaciones).');
        return;
    }

    const row = document.createElement('tr');
    row.classList.add('new-highlight');
    // CLAVE: Usamos quotation_id en lugar de order_id
    row.id = `quotation-row-${quotationData.quotation_id}`; 

    const quotationNumber = quotationData.quotation_number; // Referencia ajustada
    const quotationId = quotationData.quotation_id; 
    
    // Si el backend no devuelve la fecha formateada, usamos la fecha actual
    const quotationDateDisplay = quotationData.order_date_formatted || new Date().toLocaleDateString('es-CL'); 

    const supplierName = quotationData.supplier_name || 'Desconocido';

    const totalAmount = quotationData.total_amount || 0;
    const totalAmountDisplay = (totalAmount > 0) ? formatCurrency(totalAmount) : 'N/A';

    const creatorUsername = quotationData.creator_username || 'Usuario Actual';

    // ESTRUCTURA FINAL DE 6 COLUMNAS
    row.innerHTML = `
        <td>${quotationNumber}</td>
        <td>${supplierName}</td>
        <td>${quotationDateDisplay}</td>
        <td>${totalAmountDisplay}</td>
        <td>${creatorUsername}</td>
        <td>
            <a href="ver_cotizacion.php?id=${quotationId}" target="_blank" class="btn-view-invoice">
                <i class="ph ph-magnifying-glass"></i> Ver Detalle
            </a>
        </td>
    `;

    // Eliminar la fila de "no hay órdenes/cotizaciones" si existe
    const noOrdersRow = quotationsListBody.querySelector('tr td[colspan="6"]');
    if (noOrdersRow) {
        const parentRow = noOrdersRow.closest('tr');
        if(parentRow) parentRow.remove();
    }

    // Insertar la nueva cotización al principio
    quotationsListBody.prepend(row);

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

    // 3. Evento para el botón limpiar cotización
    if (clearQuotationBtn) {
        clearQuotationBtn.addEventListener('click', () => {
            if (INVOICE_ITEMS.length > 0 && confirm('Est&aacute;s seguro de que quieres limpiar toda la cotizaci&oacute;?')) { // Texto ajustado
                clearInvoice(false);
            } else if (INVOICE_ITEMS.length === 0) {
                showStatusMessage('La cotizaci&oacute;n ya est&aacute; vac&iacute;a.', 'info'); // Texto ajustado
            }
        });
    }

    // 4. Apertura del modal de registro final
    if (registerQuotationBtn && registerModal) {
        registerQuotationBtn.addEventListener('click', (e) => {
            e.preventDefault();
            if (INVOICE_ITEMS.length > 0) {
                updateInvoiceTotals();
                openModal(registerModal);
                document.getElementById('order_date').focus();
            } else {
                showStatusMessage('Agregue productos antes de generar la cotizaci&oacute;.', 'error'); // Texto ajustado
            }
        });
    }

    // Inicializar la tabla y los totales al cargar
    renderInvoiceItems();
});