// =========================================================
// VARIABLES GLOBALES (ASUMO que están definidas en otro lado o en PHP)
// =========================================================

// const IVA_RATE; // Tasa de IVA (e.g., 0.19 para 19%)
// const INVOICE_ITEMS = []; // Arreglo global de ítems de factura
// const currencyFormatter; // Objeto de formato de moneda (e.g., Intl.NumberFormat)
// const SUPPLIER_ID; // ID del proveedor actual
// const ALL_SUPPLIER_PRODUCTS = []; // Lista completa de productos del proveedor

// =========================================================
// FUNCIONES DE CÁLCULO (Réplicas de PHP para el Frontend)
// =========================================================

// Evita que presionar Enter dentro de cualquier input envíe el formulario
document.addEventListener('keydown', function (e) {
    if (e.key === 'Enter' && e.target.tagName === 'INPUT') {
        e.preventDefault(); // evita que el formulario se envíe o recargue
    }
});

/**
 * Redondea un valor a entero (para precios finales sin decimales).
 */
function toIntegerPrice(value) {
    return Math.round(value);
}

/**
 * Calcula el Costo Bruto (con IVA) a partir del Costo Neto.
 */
function calculateGrossCost(netCost) {
    // ⚠️ CORRECCIÓN: Usar parseFloat para el costo neto, sin redondear
    return parseFloat(netCost) * (1 + IVA_RATE);
}

/**
 * Calcula el Costo Neto (sin IVA) a partir del Costo Bruto.
 */
function calculateNetCost(grossCost) {
    // ⚠️ CORRECCIÓN: Usar parseFloat para el costo bruto, sin redondear
    const gross = parseFloat(grossCost);
    if ((1 + IVA_RATE) === 0) return 0;
    return gross / (1 + IVA_RATE);
}

/**
 * Calcula el Porcentaje de Margen Bruto (markup).
 */
function calculateMarginPercentage(netCost, salePrice) {
    // ⚠️ CORRECCIÓN: Usar parseFloat para ambos precios, sin toIntegerPrice
    salePrice = parseFloat(salePrice);
    netCost = parseFloat(netCost);
    if (salePrice <= netCost || salePrice === 0) return 0;
    return ((salePrice - netCost) / salePrice) * 100;
}

/**
 * Calcula el Precio de Venta Neto requerido para un margen deseado.
 */
function calculateSalePriceFromMargin(netCost, marginPct) {
    // ⚠️ CORRECCIÓN: Usar parseFloat para el costo neto, sin toIntegerPrice
    netCost = parseFloat(netCost);
    marginPct = parseFloat(marginPct);
    if (marginPct >= 100) return netCost;
    if (netCost <= 0) return 0;
    return netCost / (1 - (marginPct / 100));
}

/**
 * Aplica el redondeo a la centena superior y resta 10.
 */
function roundToNearestHundredMinusTen(price) {
    const roundedUp = Math.ceil(price / 100) * 100;
    // Mantenemos toIntegerPrice aquí, ya que es el redondeo FINAL del precio de venta.
    return toIntegerPrice(roundedUp - 10);
}

/**
 * Formatea un número como moneda CLP.
 */
function formatCurrency(amount) {
    // Mantenemos toIntegerPrice aquí, ya que es para mostrar el precio en CLP sin decimales.
    return currencyFormatter.format(toIntegerPrice(amount));
}


// =========================================================
// GESTIÓN DE PERSISTENCIA (LocalStorage)
// =========================================================

/**
 * Genera la clave única para LocalStorage (ahora sin el ID del proveedor).
 */
function getStorageKey() {
    return 'temp_invoice_items';
}

/**
 * Guarda el estado actual de INVOICE_ITEMS en localStorage.
 */
function saveInvoiceState() {
    if (INVOICE_ITEMS.length > 0) {
        localStorage.setItem(getStorageKey(), JSON.stringify(INVOICE_ITEMS));
    } else {
        clearInvoiceState();
    }
    updateInvoiceTotals();
}

/**
 * Carga el estado de INVOICE_ITEMS desde localStorage.
 * @returns {Array} El arreglo de ítems cargado o un arreglo vacío.
 */
function loadInvoiceState() {
    const storedItems = localStorage.getItem(getStorageKey());
    if (storedItems) {
        try {
            return JSON.parse(storedItems);
        } catch (e) {
            console.error("Error al parsear localStorage:", e);
            return [];
        }
    }
    return [];
}

/**
 * Limpia el estado de la factura de localStorage.
 */
function clearInvoiceState() {
    localStorage.removeItem(getStorageKey());
}


// =========================================================
// REFERENCIAS Y ESTADO GLOBAL
// =========================================================

const invoiceItemsBody = document.getElementById('invoice-items-body');
const subtotalNetDisplay = document.getElementById('subtotal-net');
const ivaAmountDisplay = document.getElementById('iva-amount');
const totalGrossDisplay = document.getElementById('total-gross');
const registerInvoiceBtn = document.getElementById('register-invoice-btn');
const noProductsRow = document.getElementById('no-products-row');
const clearInvoiceBtn = document.getElementById('clear-invoice-btn');

// Modales y Controles
const addProductBtn = document.getElementById('add-product-btn');
const addNewProductBtn = document.getElementById('add-new-product-btn');
const selectProductModal = document.getElementById('select-product-modal');
const addNewProductModal = document.getElementById('add-new-product-modal');
const newProductForm = document.getElementById('new-product-form');
const productSearchInput = document.getElementById('product-search');
const productGridContainer = document.getElementById('product-grid-container');
const closeModalButtons = document.querySelectorAll('.close-button');
const registerModal = document.getElementById('register-modal');
const statusMessage = document.getElementById('status-message');


// =========================================================
// FUNCIONES DE CONTROL DE MODALES Y MENSAJES
// =========================================================

function openModal(modalElement) {
    if (modalElement) {
        modalElement.classList.add('is-active');
        modalElement.setAttribute('aria-hidden', 'false');
        // Usar evento personalizado si se necesita lógica adicional al abrir
        modalElement.dispatchEvent(new Event('modal:open'));
    }
}

function closeModal(modalElement) {
    if (modalElement) {
        modalElement.classList.remove('is-active');
        modalElement.setAttribute('aria-hidden', 'true');
    }
}

/**
 * Muestra un mensaje temporal (Toast/Status) en la interfaz.
 */
function showStatusMessage(message, type = 'info', duration = 3000) {
    if (!statusMessage) return;

    statusMessage.classList.remove('is-active', 'success', 'warning', 'error', 'info');
    statusMessage.textContent = message;
    statusMessage.classList.add('is-active', type);

    setTimeout(() => {
        statusMessage.classList.remove('is-active');
    }, duration);
}


// =========================================================
// LÓGICA DE LA TABLA Y CÁLCULOS
// =========================================================

function updateInvoiceTotals() {
    let subtotalNet = 0;
    let subtotalGross = 0;

    // ASUMO que INVOICE_ITEMS existe globalmente
    INVOICE_ITEMS.forEach(item => {
        // ⚠️ CORRECCIÓN: Usar parseFloat() para Costo Neto, sin toIntegerPrice()
        const netCost = parseFloat(item.cost_price_net_new || 0); 
        
        // ⚠️ CORRECCIÓN: calculateGrossCost ahora devuelve flotante
        const grossCostPerUnit = calculateGrossCost(netCost);
        
        const quantity = parseInt(item.quantity || 0);

        // Sumar como flotantes
        subtotalNet += netCost * quantity;
        subtotalGross += grossCostPerUnit * quantity;
    });

    // Redondear los totales SOLO al final (la lógica de la factura final lo requiere entero)
    const totalGross = toIntegerPrice(subtotalGross);
    const subtotalNetRounded = toIntegerPrice(subtotalNet); // Redondear el neto total al mostrar
    
    // Si la suma de brutos es correcta, el IVA es la diferencia
    const ivaAmount = totalGross - subtotalNetRounded;

    // Usamos las versiones redondeadas para mostrar
    subtotalNetDisplay.textContent = formatCurrency(subtotalNetRounded);
    ivaAmountDisplay.textContent = formatCurrency(ivaAmount);
    totalGrossDisplay.textContent = formatCurrency(totalGross);

    registerInvoiceBtn.disabled = INVOICE_ITEMS.length === 0 || totalGross <= 0;

    const modalTotalDisplay = document.getElementById('modal-total-display');
    if (modalTotalDisplay) {
        modalTotalDisplay.textContent = formatCurrency(totalGross);
    }
}

/**
 * Maneja los cambios en Costo Neto o Costo Bruto al perder el foco (blur/change).
 * Actualiza el campo opuesto y el precio de venta.
 * @param {HTMLElement} input Elemento que disparó el evento.
 * @param {number} index Índice del ítem en INVOICE_ITEMS.
 */
function onCostInputChange(input, index) {
    const item = INVOICE_ITEMS[index];
    const row = input.closest('tr');

    const inputNetCost = row.querySelector('.input-costo-neto');
    const inputGrossCost = row.querySelector('.input-costo-bruto-nuevo');
    const inputMargin = row.querySelector('.input-margen-pct');

    let netCost = parseFloat(inputNetCost.value) || 0;
    let grossCost = parseFloat(inputGrossCost.value) || 0;
    
    // 1. LÓGICA DE ACTUALIZACIÓN INVERSA (Neto <-> Bruto)
    if (input === inputGrossCost) {
        // Ingresó Costo Bruto -> Calcular Neto
        netCost = calculateNetCost(grossCost); // Retorna flotante
        
        // 🚀 CORRECCIÓN CLAVE: Solo actualiza el campo OPUESTO y el campo actual con toFixed(2)
        inputNetCost.value = netCost.toFixed(2);
        inputGrossCost.value = grossCost.toFixed(2); // Asegura que el valor ingresado tenga 2 decimales
    }
    else { // Ingresó Costo Neto
        // Ingresó Costo Neto -> Calcular Bruto
        grossCost = calculateGrossCost(netCost); // Retorna flotante
        
        // 🚀 CORRECCIÓN CLAVE: Solo actualiza el campo OPUESTO y el campo actual con toFixed(2)
        inputGrossCost.value = grossCost.toFixed(2);
        inputNetCost.value = netCost.toFixed(2); // Asegura que el valor ingresado tenga 2 decimales
    }

    // 2. OBTENER MARGEN ACTUAL para recalcular precio de venta
    const currentMarginPct = parseFloat(inputMargin.value) || 0;

    // 3. FUERZA RECALCULO DE VENTA con el NUEVO COSTO NETO, manteniendo el MARGEN
    // Pasamos 'none' para que la función onSalePriceUpdate use el margen actual
    onSalePriceUpdate(row, index, currentMarginPct, 'margin');

    // 4. ACTUALIZAR ARREGLO GLOBAL de costos (como flotantes, no enteros)
    item.cost_price_net_new = netCost;
    item.cost_price_gross_new = grossCost;
    item.quantity = parseInt(row.querySelector('.input-quantity').value) || 0;
    
    updateInvoiceTotals();
    saveInvoiceState(); // 💾 Guardar estado en localStorage
}

/**
 * Maneja los cambios en Margen % o Precio Venta Final.
 * Solo actualiza los campos de venta/margen. No toca los costos.
 * @param {HTMLElement} input Elemento que disparó el evento.
 * @param {number} index Índice del ítem en INVOICE_ITEMS.
 */
function onSalePriceInputChange(input, index) {
    const row = input.closest('tr');
    
    const inputMargin = row.querySelector('.input-margen-pct');
    const inputFinalPrice = row.querySelector('.input-precio-final');

    // ⚠️ CORRECCIÓN: Usar parseFloat para el costo neto (que ahora viene con decimales)
    const netCost = parseFloat(row.querySelector('.input-costo-neto').value) || 0;

    let marginPct = parseFloat(inputMargin.value) || 0;
    let finalPrice = parseFloat(inputFinalPrice.value) || 0;

    // 1. Determinar el origen del cálculo (Margen o Precio Final)
    if (input === inputFinalPrice) {
        // Ingresó Precio Final -> Calcular Margen
        // ⚠️ CORRECCIÓN: Permitir decimales en el precio final ingresado, aunque luego se redondee.
        marginPct = calculateMarginPercentage(netCost, finalPrice);
        
        // El input de margen se actualiza aquí
        inputMargin.value = marginPct.toFixed(2);
        // Aseguramos que el valor ingresado tenga 2 decimales
        inputFinalPrice.value = finalPrice.toFixed(2);
        
        // Llama a la función de actualización pasando el precio final
        onSalePriceUpdate(row, index, finalPrice, 'price');
    }
    else { // Ingresó Margen %
        // Ingresó Margen -> Calcular Precio Final
        marginPct = parseFloat(marginPct.toFixed(2)); // Asegura 2 decimales
        
        inputMargin.value = marginPct;

        const suggestedNetPrice = calculateSalePriceFromMargin(netCost, marginPct);
        const suggestedGrossPrice = calculateGrossCost(suggestedNetPrice);
        // El precio final de venta aún pasa por la función de redondeo del negocio.
        finalPrice = roundToNearestHundredMinusTen(suggestedGrossPrice);

        // El input de precio final se actualiza aquí (redondeado por la lógica del negocio)
        inputFinalPrice.value = toIntegerPrice(finalPrice); 

        // Llama a la función de actualización pasando el margen
        onSalePriceUpdate(row, index, marginPct, 'margin');
    }
}

/**
 * Función interna para ejecutar el cálculo de precio de venta y actualizar la interfaz.
 * @param {HTMLElement} row Fila de la tabla.
 * @param {number} index Índice del ítem.
 * @param {number} value Valor de entrada (Margen o Precio Final).
 * @param {string} type Tipo de entrada ('margin', 'price', o 'none').
 */
function onSalePriceUpdate(row, index, value, type) {
    const item = INVOICE_ITEMS[index];
    // ⚠️ CORRECCIÓN: Usar parseFloat para el costo neto (que ahora viene con decimales)
    const netCost = parseFloat(row.querySelector('.input-costo-neto').value) || 0;
    
    let marginPct = 0;
    let finalPrice = 0;

    // Lógica para determinar el Margen y Precio Final
    if (type === 'none') {
        // Carga desde localStorage, solo usa los valores guardados
        marginPct = parseFloat(row.querySelector('.input-margen-pct').value) || 0;
        finalPrice = parseFloat(row.querySelector('.input-precio-final').value) || 0;
    } else if (type === 'margin') {
        marginPct = value;
        const suggestedNetPrice = calculateSalePriceFromMargin(netCost, marginPct);
        const suggestedGrossPrice = calculateGrossCost(suggestedNetPrice);
        finalPrice = roundToNearestHundredMinusTen(suggestedGrossPrice);
    } else { // type === 'price'
        finalPrice = value;
        marginPct = calculateMarginPercentage(netCost, finalPrice);
    }
    
    // Recalcular el valor sugerido (siempre basado en el MARGEN ACTUAL)
    const suggestedNetPrice = calculateSalePriceFromMargin(netCost, marginPct);
    const suggestedGrossPriceUnrounded = calculateGrossCost(suggestedNetPrice);
    const suggestedFinalRoundedPrice = roundToNearestHundredMinusTen(suggestedGrossPriceUnrounded); // Precio sugerido redondeado final

    // Actualizar Interfaz (Display Sugerido)
    row.querySelector('.display-precio-sug').textContent = formatCurrency(suggestedFinalRoundedPrice);

    // Actualizar Inputs (Solo si no se acaban de ingresar, o si se recalcula)
    if (type !== 'price') { 
        // Si el origen NO fue el precio final, se sobreescribe con el valor calculado
        row.querySelector('.input-precio-final').value = toIntegerPrice(finalPrice); // Mantenemos el redondeo a entero aquí
    }
    if (type !== 'margin') { 
        // Si el origen NO fue el margen, se sobreescribe con el valor calculado
        row.querySelector('.input-margen-pct').value = marginPct.toFixed(2);
    }

    // ACTUALIZAR ARREGLO GLOBAL FINAL
    item.new_margin = marginPct;
    item.new_sale_price = finalPrice;
    item.quantity = parseInt(row.querySelector('.input-quantity').value) || 0;

    updateInvoiceTotals();
    saveInvoiceState(); // 💾 Guardar estado en localStorage
}

/**
 * Lógica simplificada. Solo actualiza la cantidad y los totales.
 */
function updateRowCalculations(row, index) {
    const item = INVOICE_ITEMS[index];
    const inputQuantity = row.querySelector('.input-quantity');
    
    // 1. Actualiza SOLO el valor de cantidad en el arreglo global
    item.quantity = parseInt(inputQuantity.value) || 0;
    
    // 2. Recalcula los totales de la factura
    updateInvoiceTotals();
    saveInvoiceState(); // 💾 Guardar estado en localStorage
}

/**
 * Añade listeners a los inputs de una fila de producto.
 */
function addRowListeners(row, index) {
    const inputNetCost = row.querySelector('.input-costo-neto');
    const inputGrossCost = row.querySelector('.input-costo-bruto-nuevo');
    const inputMargin = row.querySelector('.input-margen-pct');
    const inputFinalPrice = row.querySelector('.input-precio-final');
    const inputQuantity = row.querySelector('.input-quantity');
    
    // --- Costo Neto <-> Costo Bruto (Activado al perder foco/Enter) ---
    // 🚀 CORRECCIÓN CLAVE: Usamos 'change' y 'blur' para permitir el ingreso de múltiples dígitos
    ['change', 'blur'].forEach(evt => {
        inputNetCost.addEventListener(evt, () => onCostInputChange(inputNetCost, index));
        inputGrossCost.addEventListener(evt, () => onCostInputChange(inputGrossCost, index));
    });

    // --- MARGEN % EN VIVO (Para ver el precio sugerido/final mientras se escribe) ---
    inputMargin.addEventListener('input', () => {
        const netCost = parseFloat(inputNetCost.value) || 0;
        const marginPct = parseFloat(inputMargin.value) || 0;

        // Calcula el precio en vivo sin modificar el valor del inputMargin (solo lo lee)
        const suggestedNetPrice = calculateSalePriceFromMargin(netCost, marginPct);
        const suggestedGrossPrice = calculateGrossCost(suggestedNetPrice);
        const finalPrice = roundToNearestHundredMinusTen(suggestedGrossPrice);

        // ⚠️ CORRECCIÓN: Actualiza el input de precio final y el display sugerido en tiempo real
        inputFinalPrice.value = toIntegerPrice(finalPrice); // Mantenemos redondeo a entero para la vista final
        const display = row.querySelector('.display-precio-sug');
        if (display) display.textContent = formatCurrency(finalPrice);
    });

    // --- Cálculo definitivo de Margen/Precio Final (Al perder foco/Enter) ---
    ['change', 'blur'].forEach(evt => {
        inputMargin.addEventListener(evt, () => onSalePriceInputChange(inputMargin, index));
        inputFinalPrice.addEventListener(evt, () => onSalePriceInputChange(inputFinalPrice, index));
    });

    // --- ENTER: recalcula y evita refresco (Aplica a Costo y Venta) ---
    [inputNetCost, inputGrossCost, inputMargin, inputFinalPrice].forEach(input => {
        input.addEventListener('keydown', (e) => {
            if (e.key === 'Enter') {
                e.preventDefault();
                // Si es un campo de costo, llamamos a onCostInputChange, si no, a onSalePriceInputChange
                if (e.target.classList.contains('input-costo-neto') || e.target.classList.contains('input-costo-bruto-nuevo')) {
                    onCostInputChange(e.target, index);
                } else {
                    onSalePriceInputChange(e.target, index);
                }
                e.target.blur(); // Quita el foco para completar la acción
            }
        });
    });

    // --- Cantidad (Solo llama a la función corregida) ---
    inputQuantity.addEventListener('input', () => updateRowCalculations(row, index));

    // --- Inicializa ---
    // Mantenemos la inicialización para asegurar el estado inicial de la fila
    onCostInputChange(inputNetCost, index); 
}

/**
 * 🚀 LÓGICA CORREGIDA: Agrega o INCREMENTA la cantidad de un producto.
 * @param {object} product - Datos del producto a añadir o incrementar.
 */
function addProductOrUpdateQuantity(product) {
    const productId = product.id;
    const existingItemIndex = INVOICE_ITEMS.findIndex(item => item.id == productId);

    if (existingItemIndex !== -1) {
        // Producto EXISTE: Incrementar la cantidad y actualizar la tabla
        const item = INVOICE_ITEMS[existingItemIndex];
        
        // La cantidad base para incremento es 1
        item.quantity += (product.quantity || 1); 
        
        // 1. Encontrar la fila existente en el DOM para actualizar la cantidad
        const existingRow = invoiceItemsBody.querySelector(`tr[data-product-id="${productId}"]`);
        if (existingRow) {
            const quantityInput = existingRow.querySelector('.input-quantity');
            if (quantityInput) {
                quantityInput.value = item.quantity;
            }
            // Asegurarse de que los otros campos estén actualizados
            const index = INVOICE_ITEMS.findIndex(item => item.id == productId);
            if (index !== -1) {
                updateRowCalculations(existingRow, index);
            }
        }
        
        // 2. Ejecutar los cálculos y guardado
        updateInvoiceTotals();
        saveInvoiceState();
        return true; // Indicamos que se incrementó
    } else {
        // Producto NO EXISTE: Añadir como nuevo ítem
        return renderInvoiceItem(product, false); // false para evitar llamada doble a renderProductCards
    }
}

/**
 * Añade un nuevo producto a la factura (Solo se llama si el producto no existe).
 * @param {object} product - Datos del producto a añadir.
 * @param {boolean} shouldRenderCards - Indica si se debe re-renderizar la grilla. Por defecto true.
 */
function renderInvoiceItem(product, shouldRenderCards = true) {
    if (INVOICE_ITEMS.some(item => item.id == product.id)) {
        return; 
    }
    
    if (noProductsRow) {
        const currentNoProductsRow = invoiceItemsBody.querySelector('#no-products-row');
        if (currentNoProductsRow) {
             currentNoProductsRow.style.display = 'none';
        }
    }

    const productId = product.id;
    // ⚠️ CAMBIO: Usar parseFloat() para los costos de producto (sin redondear aquí)
    const initialNetCost = parseFloat(product.cost_price_net);
    const initialGrossCost = calculateGrossCost(initialNetCost); // Ahora devuelve flotante

    const newItem = {
        id: productId,
        code: product.code,
        name: product.name,
        // Al agregar, la cantidad viene del modal de nuevo producto o es 1 si es de la grilla
        quantity: product.quantity || 1, 
        cost_price_net_new: initialNetCost,
        cost_price_gross_new: initialGrossCost,
        new_sale_price: product.current_sale_price || 0,
        new_margin: product.current_margin || 0, // Se inicializa con el margen/precio de venta actual
        is_new: product.is_new || false,
        // Almacenamos el costo/precio actual para referencia
        current_cost_price_net: parseFloat(product.cost_price_net), // ⚠️ CORRECCIÓN: Almacenar como flotante
        current_cost_price_gross: calculateGrossCost(product.cost_price_net), // ⚠️ CORRECCIÓN: Almacenar como flotante
        current_sale_price: parseFloat(product.current_sale_price), // ⚠️ CORRECCIÓN: Almacenar como flotante
        current_margin: product.current_margin || 0,
    };

    INVOICE_ITEMS.push(newItem);
    const index = INVOICE_ITEMS.length - 1;

    // Calcular el margen inicial si no existe
    const initialMargin = newItem.new_margin
        ? newItem.new_margin.toFixed(2)
        : calculateMarginPercentage(initialNetCost, newItem.new_sale_price).toFixed(2);

    const row = document.createElement('tr');
    row.dataset.productId = productId;

    row.innerHTML = `
        <td class="col-code">${product.code}</td>
        <td class="col-product">${product.name} ${product.is_new ? '<span class="tag-new">(NUEVO)</span>' : ''}</td>
        <td class="col-stock">${product.stock || 0}</td>

        <td class="text-right">${formatCurrency(newItem.current_cost_price_net)}</td>
        <td class="text-right">${formatCurrency(newItem.current_cost_price_gross)}</td>
        <td class="text-right">${newItem.current_margin.toFixed(2)}%</td>
        <td class="text-right">${formatCurrency(newItem.current_sale_price)}</td>

        <td>
            <input type="number" class="invoice-input input-costo-neto" value="${initialNetCost.toFixed(2)}" step="0.01" min="0" required>
        </td>
        <td>
            <input type="number" class="invoice-input input-costo-bruto-nuevo" value="${initialGrossCost.toFixed(2)}" step="0.01" min="0" required>
        </td>
        <td>
            <input type="number" class="invoice-input input-margen-pct" value="${initialMargin}" step="0.01" min="0" required>
        </td>
        <td>
            <span class="display-precio-sug">--</span>
        </td>
        <td>
            <input type="number" class="invoice-input input-precio-final" value="${toIntegerPrice(newItem.new_sale_price)}" step="0.01" min="0" required>
        </td>
        <td>
            <input type="number" class="invoice-input input-quantity" value="${newItem.quantity}" step="1" min="1" required>
        </td>
        <td>
            <button class="btn-remove btn-danger" data-id="${productId}">LIMPIAR</button>
        </td>
    `;

    invoiceItemsBody.appendChild(row);
    if(shouldRenderCards) {
        // La re-renderización de la grilla aquí ya no desactiva la tarjeta, solo actualiza
        renderProductCards(ALL_SUPPLIER_PRODUCTS); 
    }
    addRowListeners(row, index);
    saveInvoiceState(); // 💾 Guardar estado en localStorage después de agregar
    return true; // Indicamos que se agregó
}

function removeInvoiceItem(productId) {
    const initialLength = INVOICE_ITEMS.length;

    // Filtrar los ítems para crear la nueva lista sin el producto eliminado
    const newItems = INVOICE_ITEMS.filter(item => item.id != productId);

    if (newItems.length === initialLength) {
        showStatusMessage('Error: Producto no encontrado en la factura.', 'error');
        return;
    }

    // Actualizar INVOICE_ITEMS en su lugar (manteniendo la referencia)
    INVOICE_ITEMS.length = 0;
    INVOICE_ITEMS.push(...newItems);

    const rowToRemove = invoiceItemsBody.querySelector(`tr[data-product-id="${productId}"]`);
    if (rowToRemove) {
        rowToRemove.remove();
    }

    if (INVOICE_ITEMS.length === 0 && noProductsRow) {
        // Asegurarse de que el elemento existe antes de intentar añadirlo/mostrarlo
        const currentNoProductsRow = invoiceItemsBody.querySelector('#no-products-row');
        if (!currentNoProductsRow) {
             invoiceItemsBody.appendChild(noProductsRow);
        }
        noProductsRow.style.display = 'table-row';
    }

    updateInvoiceTotals();
    saveInvoiceState(); // 💾 Guardar estado en localStorage después de eliminar
    
    // 🚨 CLAVE: Volvemos a renderizar la grilla. Ahora la tarjeta del producto eliminado
    // tendrá el badge de cantidad en 0.
    renderProductCards(ALL_SUPPLIER_PRODUCTS); 
    showStatusMessage('Producto eliminado de la factura.', 'warning', 2000);
}

function clearInvoice(suppressMessage = false) {
    INVOICE_ITEMS.length = 0;

    invoiceItemsBody.innerHTML = '';

    if (noProductsRow) {
        // Asegurarse de que el elemento existe antes de intentar añadirlo/mostrarlo
        invoiceItemsBody.appendChild(noProductsRow);
        noProductsRow.style.display = 'table-row';
    }

    updateInvoiceTotals();
    clearInvoiceState(); // 🗑️ Limpiar localStorage
    
    // 🚨 CLAVE: Re-renderizar la grilla para que todos los productos se habiliten
    renderProductCards(ALL_SUPPLIER_PRODUCTS); 

    if (!suppressMessage) {
        showStatusMessage('La factura ha sido limpiada.', 'info');
    }
}

/**
 * Re-renderiza la tabla completa a partir de los datos en INVOICE_ITEMS (desde localStorage).
 */
function reRenderInvoice() {
    invoiceItemsBody.innerHTML = ''; // Limpia la tabla visualmente

    if (INVOICE_ITEMS.length === 0) {
        if (noProductsRow) {
            invoiceItemsBody.appendChild(noProductsRow);
            noProductsRow.style.display = 'table-row';
        }
        updateInvoiceTotals();
        return;
    }

    INVOICE_ITEMS.forEach((item, index) => {
        // Usamos los valores guardados en el 'item' para reconstruir la fila
        
        const initialMargin = item.new_margin ? item.new_margin.toFixed(2) : '0.00';
        
        // Cargar los valores actuales del producto para la columna de "Antes"
        // ⚠️ CORRECCIÓN: Usamos parseFloat para los valores almacenados
        const currentNetCost = parseFloat(item.current_cost_price_net) || 0;
        const currentGrossCost = parseFloat(item.current_cost_price_gross) || 0;
        const currentMargin = parseFloat(item.current_margin) || 0;
        const currentSalePrice = parseFloat(item.current_sale_price) || 0;


        const row = document.createElement('tr');
        row.dataset.productId = item.id;

        // **IMPORTANTE: Usar los valores guardados (item.cost_price_net_new, etc.) en los inputs**
        row.innerHTML = `
            <td class="col-code">${item.code}</td>
            <td class="col-product">${item.name} ${item.is_new ? '<span class="tag-new">(NUEVO)</span>' : ''}</td>
            <td class="col-stock">${item.stock || 0}</td>

            <td class="text-right">${formatCurrency(currentNetCost)}</td>
            <td class="text-right">${formatCurrency(currentGrossCost)}</td>
            <td class="text-right">${currentMargin.toFixed(2)}%</td>
            <td class="text-right">${formatCurrency(currentSalePrice)}</td>

            <td>
                <input type="number" class="invoice-input input-costo-neto" value="${parseFloat(item.cost_price_net_new).toFixed(2)}" step="0.01" min="0" required>
            </td>
            <td>
                <input type="number" class="invoice-input input-costo-bruto-nuevo" value="${parseFloat(item.cost_price_gross_new).toFixed(2)}" step="0.01" min="0" required>
            </td>
            <td>
                <input type="number" class="invoice-input input-margen-pct" value="${initialMargin}" step="0.01" min="0" required>
            </td>
            <td>
                <span class="display-precio-sug">--</span>
            </td>
            <td>
                <input type="number" class="invoice-input input-precio-final" value="${toIntegerPrice(item.new_sale_price)}" step="0.01" min="0" required>
            </td>
            <td>
                <input type="number" class="invoice-input input-quantity" value="${item.quantity}" step="1" min="1" required>
            </td>
            <td>
                <button class="btn-remove btn-danger" data-id="${item.id}">LIMPIAR</button>
            </td>
        `;

        invoiceItemsBody.appendChild(row);
        addRowListeners(row, index);
    });
    // 🚨 CLAVE: Renderizar la grilla al recargar la tabla para actualizar el estado visual
    renderProductCards(ALL_SUPPLIER_PRODUCTS); 
}


// =========================================================
// LÓGICA DE MODAL DE SELECCIÓN Y NUEVO PRODUCTO (Con Grilla de Tarjetas)
// =========================================================

/**
 * Renderiza las tarjetas de producto en el modal de selección.
 * @param {Array<object>} products - Lista de productos filtrados (ALL_SUPPLIER_PRODUCTS).
 */
function renderProductCards(products) {
    if (!productGridContainer) return;

    // Solo mostramos productos existentes (los temporales de nuevo producto NO se muestran aquí)
    const productsToRender = products.filter(p => typeof p.id === 'number'); 
    
    // Filtrado por búsqueda
    const searchTerm = productSearchInput ? productSearchInput.value.toLowerCase().trim() : '';
    let finalProducts = productsToRender;

    if (searchTerm.length > 0) {
        finalProducts = productsToRender.filter(product => {
            return product.name.toLowerCase().includes(searchTerm) ||
                           product.code.toLowerCase().includes(searchTerm);
        });
    }

    if (finalProducts.length === 0) {
        productGridContainer.innerHTML = '<p class="search-info">No se encontraron productos que coincidan con la búsqueda, o no hay productos existentes para este proveedor.</p>';
        return;
    }

    const cardsHtml = finalProducts.map(product => {
        const cardClass = 'product-card';
        
        // Usamos un valor de fallback para la imagen si no existe
        const imageUrl = product.image_url && product.image_url !== 'null' ? product.image_url : '../img/placeholder-product.png';
        
        // ⚠️ CORRECCIÓN: Usamos el costo_price_net como flotante para el formatCurrency
        const currentCostNet = formatCurrency(parseFloat(product.cost_price_net)); 
        
        // Determinar la cantidad actual para mostrar un feedback visual si está en factura
        const itemInInvoice = INVOICE_ITEMS.find(item => item.id == product.id);
        const quantityInInvoice = itemInInvoice ? itemInInvoice.quantity : 0;
        const badge = quantityInInvoice > 0 
            ? `<span class="badge-quantity">${quantityInInvoice} en factura</span>` 
            : '';

        return `
            <div class="${cardClass}" 
                 data-product-id="${product.id}" 
                 data-product-name="${product.name}" 
                 title="Clic para agregar/incrementar cantidad.">
                
                <div class="product-image-container">
                    <img src="${imageUrl}" alt="${product.name}">
                    ${badge}
                </div>
                
                <div class="product-info">
                    <h4 class="product-name">${product.name}</h4>
                    <p class="product-stock">Stock: <strong>${product.stock || 0}</strong> | Cód: <span>${product.code}</span></p>
                    <p class="product-cost">Costo Act. Neto: <strong>${currentCostNet}</strong></p>
                </div>
            </div>
        `;
    }).join('');

    productGridContainer.innerHTML = cardsHtml;

    // 🚀 CLAVE: Adjuntar listeners de clic sin cerrar el modal y con scroll
    productGridContainer.querySelectorAll('.product-card').forEach(card => {
        card.addEventListener('click', () => {
            const productId = parseInt(card.getAttribute('data-product-id'));
            const product = ALL_SUPPLIER_PRODUCTS.find(p => p.id === productId);

            if (product) {
                const success = addProductOrUpdateQuantity(product);
                
                if (success) {
                    const lastRow = invoiceItemsBody.querySelector(`tr[data-product-id="${productId}"]`);
                    
                    if(lastRow) {
                        // 1. Intentar enfocar
                        lastRow.querySelector('.input-quantity').focus(); 
                        
                        // 2. 🚨 CORRECCIÓN: Forzar el scroll a la fila en la tabla principal.
                        lastRow.scrollIntoView({ 
                            behavior: 'smooth', 
                            block: 'center' 
                        });
                    }
                    
                    // 3. Actualizar el estado visual del modal (badge de cantidad)
                    renderProductCards(ALL_SUPPLIER_PRODUCTS); 
                    
                    const item = INVOICE_ITEMS.find(i => i.id == productId);
                    showStatusMessage(`Se agregó 1 unidad de "${product.name}". Total: ${item.quantity} und.`, 'success', 1500);
                }
            }
        });
    });
}

function handleProductSearch() {
    // La función renderProductCards ahora maneja el filtrado por el valor del input
    renderProductCards(ALL_SUPPLIER_PRODUCTS);
}

function updateNewProductPrice() {
    const newProductCostNet = document.getElementById('new_product_cost_net');
    const newProductMarginPct = document.getElementById('new_product_margin_pct');
    const newProductPriceFinal = document.getElementById('new_product_price_final');

    const netCost = parseFloat(newProductCostNet?.value) || 0;
    const marginPct = parseFloat(newProductMarginPct?.value) || 0;

    const suggestedNetPrice = calculateSalePriceFromMargin(netCost, marginPct);
    const suggestedGrossPrice = calculateGrossCost(suggestedNetPrice);
    const finalPrice = roundToNearestHundredMinusTen(suggestedGrossPrice);

    if (newProductPriceFinal) {
        newProductPriceFinal.value = finalPrice;
    }
}

function handleNewProductSubmission(e) {
    e.preventDefault();

    const code = document.getElementById('new_product_code').value.trim();
    const name = document.getElementById('new_product_name').value.trim();
    const quantity = parseInt(document.getElementById('new_product_quantity')?.value) || 0;

    const costNet = parseFloat(document.getElementById('new_product_cost_net').value) || 0;
    const marginPct = parseFloat(document.getElementById('new_product_margin_pct').value) || 0;
    // ⚠️ CORRECCIÓN: Usar parseFloat para leer el valor del input, aunque luego se redondee a entero
    const priceFinal = parseFloat(document.getElementById('new_product_price_final').value) || 0; 

    if (!code || !name || costNet <= 0 || quantity <= 0 || priceFinal <= 0) {
        showStatusMessage('Por favor, rellene todos los campos (código, nombre, cantidad, costo neto, margen) y asegúrese de que los valores sean positivos.', 'error');
        return;
    }

    // Usar un ID temporal único que empieza con 'NEW_' para distinguirlo
    const tempId = 'NEW_' + Date.now(); 

    // Comprobación de duplicidad por código solo para productos NUEVOS
    if (INVOICE_ITEMS.some(item => item.code === code && item.is_new)) {
        showStatusMessage(`El producto nuevo con código ${code} ya está en la factura.`, 'warning');
        return;
    }

    const newProduct = {
        id: tempId,
        code: code,
        name: name,
        stock: 0,
        // ⚠️ CORRECCIÓN: Almacenar los costos como flotantes
        cost_price_net: costNet,
        cost_price_gross: calculateGrossCost(costNet),
        current_margin: marginPct,
        current_sale_price: toIntegerPrice(priceFinal), // El precio final es entero por la lógica de negocio
        quantity: quantity,
        is_new: true,
        image_url: null, 
        current_cost_price_net: costNet, 
        current_cost_price_gross: calculateGrossCost(costNet),
    };
    
    // 🚀 CLAVE: Usamos la función de adición centralizada
    addProductOrUpdateQuantity(newProduct);

    // Limpiar formulario y cerrar modal
    newProductForm.reset();
    closeModal(addNewProductModal);
    showStatusMessage(`Producto nuevo "${name}" añadido a la factura.`, 'success');
}


// =========================================================
// GESTIÓN DE ENVÍO DE FACTURA (AJAX)
// =========================================================
/**
 * Actualiza la tabla del historial de facturas mediante una solicitud AJAX (fetch).
 * Requiere que la variable global 'SUPPLIER_ID' esté definida.
 */
function updateInvoiceHistoryTable() {
    console.log("Factura registrada. Ejecutando función para actualizar el historial de facturas...");

    // 1. Obtiene el contenedor donde se inyectará el nuevo HTML de la tabla.
    const historyContainer = document.getElementById('invoice-history-container'); 
    
    // Fallback: Si no encontramos el contenedor (por un error de ID o estructura), recargamos toda la página.
    if (!historyContainer) {
        console.error("Error: El contenedor de historial ('#invoice-history-container') no fue encontrado en el DOM. Recargando página.");
        window.location.reload(); 
        return;
    }

    // 2. Muestra un mensaje de carga temporal mientras se espera la respuesta del servidor.
    historyContainer.innerHTML = '<p style="text-align: center; padding: 20px;">Cargando historial de facturas...</p>';

    // 3. Usa Fetch para solicitar el HTML actualizado de la tabla.
    // ASUMO que 'get_invoice_history.php' devolverá el HTML completo de la tabla.
    fetch('get_invoice_history.php?supplier_id=' + SUPPLIER_ID) 
        .then(response => {
            if (!response.ok) {
                // Lanza un error si el estado HTTP no es 200-299
                throw new Error(`Error HTTP: ${response.status}. No se pudo cargar el historial.`);
            }
            // Esperamos la respuesta como texto/HTML
            return response.text(); 
        })
        .then(htmlContent => {
            // 4. Inyecta el nuevo HTML (la tabla actualizada) en el contenedor.
            historyContainer.innerHTML = htmlContent;
        })
        .catch(error => {
            // Manejo de errores de red o servidor
            console.error('Error al actualizar el historial de facturas:', error);
            historyContainer.innerHTML = '<p style="color: red; text-align: center; padding: 20px;">Error al cargar el historial. Intenta recargar la página.</p>';
        });
}
/**
 * 🚀 FUNCIÓN FINAL: Envía la factura final al backend y recarga el historial.
 */
function handleFinalInvoiceSubmission(e) {
    e.preventDefault();
    
    const finalInvoiceForm = document.getElementById('final-invoice-form');
    const submitButton = finalInvoiceForm.querySelector('button[type="submit"]');
    if (submitButton) submitButton.disabled = true; 

    const invoiceNumber = document.getElementById('invoice_number').value.trim();
    const invoiceDate = document.getElementById('invoice_date').value.trim();
    const supplierId = document.getElementById('final-supplier-id').value;
    // ⚠️ CORRECCIÓN: Calcular el total con flotantes antes de redondear a entero para el envío final
    const totalGross = INVOICE_ITEMS.reduce((sum, item) => sum + (parseFloat(item.cost_price_gross_new || 0) * item.quantity), 0); 

    if (!invoiceNumber || !invoiceDate) {
        showStatusMessage('Debes ingresar el número y la fecha de la factura.', 'error');
        if (submitButton) submitButton.disabled = false;
        return;
    }

    const payload = {
        supplier_id: supplierId,
        invoice_number: invoiceNumber,
        invoice_date: invoiceDate,
        total_amount: toIntegerPrice(totalGross),
        items: INVOICE_ITEMS.map(item => ({
            product_id: item.is_new ? null : item.id,
            is_new: item.is_new,
            code: item.code,
            name: item.name,
            quantity: item.quantity,
            // ⚠️ CORRECCIÓN: Enviar los costos con precisión decimal al backend
            cost_price_net: parseFloat(item.cost_price_net_new).toFixed(2), 
            new_sale_price: toIntegerPrice(item.new_sale_price),
            new_margin: parseFloat(item.new_margin).toFixed(2),
        }))
    };

    showStatusMessage('Registrando factura...', 'info', 10000); // Muestra mensaje de carga

    fetch('process_invoice.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify(payload),
    })
    .then(response => {
        if (!response.ok) {
            throw new Error(`Error HTTP: ${response.status}`);
        }
        return response.json();
    })
    .then(data => {
        if (submitButton) submitButton.disabled = false;

        if (data.success) {
            // 1. Mostrar TOAST VERDE
            showStatusMessage('Factura registrada con éxito y productos actualizados.', 'success', 3500);
            
            // 2. Limpiar la factura (estado local y visual)
            clearInvoice(true); 
            closeModal(registerModal);
            finalInvoiceForm.reset();
            
            // 3. 🚨 CLAVE: Recargar solo el historial después de un breve retraso
            setTimeout(() => {
                 updateInvoiceHistoryTable(); // Llama a la función que actualiza el historial
            }, 500); 
        } else {
            showStatusMessage('Error al registrar factura: ' + (data.message || 'Error desconocido.'), 'error', 8000);
        }
    })
    .catch(error => {
        if (submitButton) submitButton.disabled = false;
        console.error('Error de red o servidor:', error);
        showStatusMessage('Error de conexión con el servidor. Consulta la consola.', 'error', 8000);
    });
}


// =========================================================
// INICIALIZACIÓN (DOM Ready)
// =========================================================

document.addEventListener('DOMContentLoaded', () => {
    // 1. Cargar estado de la factura si existe
    const storedItems = loadInvoiceState();
    if (storedItems.length > 0) {
        INVOICE_ITEMS.push(...storedItems);
        reRenderInvoice();
        showStatusMessage(`Factura de proveedor #${SUPPLIER_ID} recuperada de la sesión anterior.`, 'info', 5000);
    } else {
        updateInvoiceTotals(); // Inicializa los totales a $0 si no hay ítems
    }

    // 2. Manejo de Modales (General)
    document.querySelectorAll('[data-modal-target]').forEach(button => {
        button.addEventListener('click', (e) => {
            // 🛑 Evitar cualquier acción o propagación indeseada.
            e.preventDefault(); 
            e.stopPropagation();

            const modalId = e.target.closest('[data-modal-target]').getAttribute('data-modal-target');
            const modal = document.getElementById(modalId);
            if (modal) {
                openModal(modal);
            }
        });
    });

    closeModalButtons.forEach(button => {
        button.addEventListener('click', (e) => {
            // 🛑 Evitar propagación para asegurar que solo el botón de cerrar funciona
            e.preventDefault(); 
            e.stopPropagation(); 

            const modal = e.target.closest('.modal');
            if (modal) {
                closeModal(modal);
            }
        });
    });

    // 3. Listeners de la Factura
    invoiceItemsBody.addEventListener('click', (e) => {
        if (e.target.classList.contains('btn-remove')) {
            const productId = e.target.getAttribute('data-id');
            removeInvoiceItem(productId);
        }
    });

    clearInvoiceBtn.addEventListener('click', () => {
        if (confirm('¿Estás seguro de que quieres LIMPIAR TODA la factura? Esto borrará todos los productos agregados.')) {
            clearInvoice();
        }
    });

    // 4. Lógica del Modal de Selección
    
    // a) Búsqueda en vivo de productos (Grilla)
    if (productSearchInput) {
        productSearchInput.addEventListener('input', handleProductSearch);
    }
    
    // b) Asegurar que la grilla se cargue al abrir el modal de selección
    if (selectProductModal) {
        selectProductModal.addEventListener('modal:open', () => {
            if (productSearchInput) productSearchInput.value = '';
            renderProductCards(ALL_SUPPLIER_PRODUCTS);
        });
    }

    // c) Calculadora de nuevo producto
    const newProductCostNet = document.getElementById('new_product_cost_net');
    const newProductMarginPct = document.getElementById('new_product_margin_pct');
    if (newProductCostNet && newProductMarginPct) {
        // Inicializar el precio de venta sugerido al cargar
        updateNewProductPrice(); 	
        newProductCostNet.addEventListener('input', updateNewProductPrice);
        newProductMarginPct.addEventListener('input', updateNewProductPrice);
    }
    newProductForm?.addEventListener('submit', handleNewProductSubmission);
    
    // 5. Apertura del Modal de Confirmación
    registerInvoiceBtn.addEventListener('click', (e) => {
        // 🚀 CORRECCIÓN CLAVE: Detener la propagación para evitar que el modal se cierre al instante.
        e.preventDefault(); 
        e.stopPropagation(); 
        
        if (INVOICE_ITEMS.length > 0) {
            // Se debe establecer el valor del input oculto con el ID del proveedor final
            const finalSupplierIdInput = document.getElementById('final-supplier-id');
            if (finalSupplierIdInput) {
                finalSupplierIdInput.value = SUPPLIER_ID;
            }
            openModal(registerModal);
        } else {
            showStatusMessage('Agrega productos a la factura antes de registrar.', 'warning');
        }
    });
    
    // 6. Listener para el envío final de la factura (AJAX o fetch)
    const finalInvoiceForm = document.getElementById('final-invoice-form');
    if (finalInvoiceForm) {
        finalInvoiceForm.addEventListener('submit', handleFinalInvoiceSubmission);
    }
});