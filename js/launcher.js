document.addEventListener('DOMContentLoaded', () => {
    const searchInput = document.getElementById('appSearch');
    const appGrid = document.getElementById('appGrid');
    const appLinks = appGrid.querySelectorAll('.app');

    // Función de Filtrado
    const filterApps = () => {
        const searchText = searchInput.value.toLowerCase().trim();

        appLinks.forEach(app => {
            // Busca en el texto del contenido del módulo (incluye nombre y descripción)
            const appText = app.textContent.toLowerCase();
            
            if (appText.includes(searchText)) {
                app.style.display = ''; // Mostrar
            } else {
                app.style.display = 'none'; // Ocultar
            }
        });
    };

    // 1. Mostrar el buscador y enfocarlo (y bloqueo de Borrar/Delete)
    document.addEventListener('keydown', (event) => {
        
        // Ignorar si el foco ya está en el buscador o si son teclas de modificador
        if (event.target === searchInput || event.ctrlKey || event.altKey || event.metaKey) {
            
            // ✅ CORRECCIÓN CLAVE: Bloquear Backspace/Delete si el buscador está enfocado y vacío.
            if ((event.key === 'Backspace' || event.key === 'Delete') && event.target === searchInput && searchInput.value.trim() === '') {
                event.preventDefault(); 
                // Si está vacío y presiona borrar, no pasa nada y se evita el rebote.
            }
            return;
        }

        // Si la tecla presionada es una letra, número, o espacio (una entrada de texto)
        if (event.key.length === 1 && !event.defaultPrevented) { 
            
            // Evitamos que la letra se escriba en el documento antes de enviarla al buscador.
            event.preventDefault(); 
            
            // 1. Mostrar y enfocar el campo de búsqueda (si está oculto)
            if (searchInput.classList.contains('hidden-search')) {
                searchInput.classList.remove('hidden-search');
            }
            searchInput.focus();
            
            // 2. Escribir la letra capturada en el campo (simulando la escritura)
            searchInput.value += event.key;
            
            // 3. Realizar el filtrado inmediatamente
            filterApps();
        }
    });

    // 2. Realizar el filtrado en vivo al escribir/borrar en el campo
    // Esto es necesario porque el document.addEventListener('keydown') solo maneja la primera letra
    // y la captura del valor cuando no está enfocado.
    searchInput.addEventListener('input', filterApps);
    
    // 3. Ocultar el buscador si queda vacío o con Escape
    searchInput.addEventListener('keyup', (event) => {

        const isBackspaceOrDelete = event.key === 'Backspace' || event.key === 'Delete';
        const isEscape = event.key === 'Escape';

        // Lógica de Ocultar si está vacío
        if (isBackspaceOrDelete && searchInput.value.trim() === '') {
            // 🚨 Si el campo está vacío, lo ocultamos y reseteamos la vista.
            searchInput.classList.add('hidden-search');
            searchInput.blur(); 
            filterApps();
            return; // Detenemos la ejecución
        } 
        
        // Lógica de Escape
        if (isEscape) {
            // Presionar Escape siempre borra el texto y oculta el buscador
            searchInput.value = '';
            searchInput.classList.add('hidden-search');
            searchInput.blur();
            filterApps();
            return; // Detenemos la ejecución
        }

        // Nota: El filtrado ya está cubierto por el evento 'input'.

    });
});