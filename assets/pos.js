// La variable 'cart' se define en el PHP antes de este script
// let cart = <?php echo json_encode($_SESSION['cart']); ?>;

// Render del carrito
function renderCart() {
  const tbody = document.querySelector('#cart-table tbody');
  tbody.innerHTML = '';
  let total = 0;
  for (const id in cart) {
    const item = cart[id];
    const subtotal = item.price * item.quantity;
    total += subtotal;
    const tr = document.createElement('tr');
    tr.innerHTML = `
      <td>${item.name}</td>
      <td>${item.price.toLocaleString()}</td>
      <td><input type="number" min="1" value="${item.quantity}" data-id="${id}" class="qty"></td>
      <td>${subtotal.toLocaleString()}</td>
      <td><button data-id="${id}" class="remove">Quitar</button></td>
    `;
    tbody.appendChild(tr);
  }
  document.getElementById('total').innerText = total.toLocaleString();
  actualizarCambio(); // Actualiza el cambio cada vez que se renderiza el carrito
  attachEvents();
}

function attachEvents() {
  document.querySelectorAll('.qty').forEach(input => {
    input.onchange = () => {
      const id = input.dataset.id;
      const qty = parseInt(input.value);
      updateCart(id, qty);
    };
  });
  document.querySelectorAll('.remove').forEach(btn => {
    btn.onclick = () => {
      const id = btn.dataset.id;
      updateCart(id, 0);
    };
  });
}

function updateCart(id, qty) {
  const form = new FormData();
  form.append('action', 'update');
  form.append('id', id);
  form.append('quantity', qty);
  fetch('api/cart.php', { method: 'POST', body: form })
    .then(res => res.json())
    .then(data => { cart = data; renderCart(); });
}

// Buscar productos con debounce
const barcodeInput = document.getElementById('barcode');
const suggestions = document.getElementById('suggestions');
let searchTimeout;

barcodeInput.addEventListener('input', function() {
  clearTimeout(searchTimeout);
  const q = this.value.trim();
  if (!q) {
    suggestions.innerHTML = '';
    return;
  }
  searchTimeout = setTimeout(() => {
    fetch(`api/products_search.php?q=${encodeURIComponent(q)}`)
      .then(res => res.json())
      .then(data => {
        suggestions.innerHTML = '';
        data.forEach(p => {
          const div = document.createElement('div');
          div.textContent = `${p.name} (${p.barcode})`;
          div.onclick = () => addToCart(p.id);
          suggestions.appendChild(div);
        });
        // Lógica para escaneo de código de barras
        if (data.length === 1 && data[0].barcode === q) {
            addToCart(data[0].id);
        }
      });
  }, 300); // 300ms de retardo
});

function addToCart(id) {
  const form = new FormData();
  form.append('action', 'add');
  form.append('id', id);
  fetch('api/cart.php', { method: 'POST', body: form })
    .then(res => res.json())
    .then(data => {
      cart = data;
      renderCart();
      barcodeInput.value = '';
      suggestions.innerHTML = '';
      barcodeInput.focus();
    });
}

// Método de pago
const paymentMethod = document.getElementById('payment-method');
const efectivoGroup = document.getElementById('efectivo-group');
const tarjetaGroup = document.getElementById('tarjeta-group');
const paidInput = document.getElementById('paid');
const voucherInput = document.getElementById('voucher');
const changeSpan = document.getElementById('change');

function actualizarCambio() {
  const total = parseInt(document.getElementById('total').innerText.replace(/\./g, '')) || 0;
  const pago = parseInt(paidInput.value) || 0;
  const cambio = pago - total;
  changeSpan.innerText = cambio > 0 ? cambio.toLocaleString() : '0';
}

function actualizarMetodoPago() {
  if (paymentMethod.value === 'efectivo') {
    efectivoGroup.style.display = 'block';
    tarjetaGroup.style.display = 'none';
    paidInput.disabled = false; // Aseguramos que siempre quede habilitado
    if (voucherInput) voucherInput.disabled = true;
  } else {
    efectivoGroup.style.display = 'none';
    tarjetaGroup.style.display = 'block';
    paidInput.disabled = false; // 👈 Cambiado: antes era "true"
    if (voucherInput) voucherInput.disabled = true;
  }
}
paymentMethod.addEventListener('change', actualizarMetodoPago);
paidInput.addEventListener('input', actualizarCambio);
actualizarMetodoPago();

// Finalizar venta
document.getElementById('finalize').onclick = () => {
  const method = paymentMethod.value;
  let paidAmount = 0;
  let voucherRef = '';
  const total = parseInt(document.getElementById('total').innerText.replace(/\./g, '')) || 0;
  
  if (total <= 0) {
      alert('El carrito está vacío.');
      return;
  }
  
  if (method === 'efectivo') {
    paidAmount = parseInt(paidInput.value) || 0;
    if (paidAmount < total) {
      alert('El monto recibido es insuficiente.');
      return;
    }
  } else {
    voucherRef = voucherInput.value.trim();
    if (!voucherRef) {
      alert('Ingrese la referencia del voucher');
      return;
    }
    paidAmount = total;
  }

  const form = new FormData();
  form.append('finalize', '1');
  form.append('paid', paidAmount);
  form.append('method', method);
  form.append('voucher', voucherRef);
  
  fetch('pos.php', { method: 'POST', body: form })
    .then(() => window.location.reload());
};

// Limpiar carrito
document.getElementById('clear-cart').onclick = () => {
  const form = new FormData();
  form.append('action', 'clear');
  fetch('api/cart.php', { method: 'POST', body: form })
    .then(() => {
      cart = {};
      renderCart();
    });
};

// Render inicial
renderCart();
barcodeInput.focus();
barcodeInput.addEventListener('blur', () => barcodeInput.focus());