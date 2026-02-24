// ==================== ИНИЦИАЛИЗАЦИЯ ТАБЛИЦ ====================

let tableHandlersInitialized = false;

function initializeTableHandlers() {
    if (tableHandlersInitialized) {
        console.log('Table handlers already initialized');
        return;
    }
    
    console.log('=== INITIALIZING TABLE HANDLERS ===');
    
    // Единый обработчик для кнопок таблицы с одной колонкой
    document.addEventListener('click', function(e) {
        const target = e.target;
        const button = target.closest('.format-btn');
        
        if (!button) return;
        
        const buttonId = button.id;
        console.log('Table button clicked:', buttonId);
        
        // ТОЛЬКО таблица с одной колонкой
        switch(buttonId) {
            case 'insertTableBtn':
                e.preventDefault();
                e.stopPropagation();
                console.log('=== INSERT TABLE CALLED ===');
                handleInsertTable();
                break;
                
            case 'deleteTableBtn':
                e.preventDefault();
                e.stopPropagation();
                console.log('=== DELETE TABLE CALLED ===');
                handleDeleteTable();
                break;
                
            case 'zakladkaBtnOne':
                e.preventDefault();
                e.stopPropagation();
                console.log('=== ZAKLADKA BUTTON CALLED ===');
                toggleZakladkaClassOne();
                break;
        }
    });
    
    tableHandlersInitialized = true;
    console.log('=== TABLE HANDLERS INITIALIZED ===');
}

// Инициализация при загрузке
document.addEventListener('DOMContentLoaded', function() {
    initializeTableHandlers();
});

// Функция для переинициализации
window.reinitializeTableHandlers = function() {
    console.log('Reinitializing table handlers');
    tableHandlersInitialized = false;
    initializeTableHandlers();
};

// ==================== ОСНОВНЫЕ ФУНКЦИИ ТАБЛИЦЫ С ОДНОЙ КОЛОНКОЙ ====================

function handleInsertTable() {
    console.log('=== handleInsertTable called ===');
    
    if (!isCursorInValidPosition()) {
        showCursorAlert();
        return;
    }
    
    const editor = getActiveEditor();
    if (editor) {
        insertTable(editor);
    }
}

function getActiveEditor() {
    const activeDialog = document.querySelector('.dialog-item.active');
    if (activeDialog) {
        return activeDialog.querySelector('.file-content-editor');
    }
    return document.querySelector('.file-content-editor');
}

function insertTable(editor) {
    console.log('=== insertTable called ===');
    
    fetch('/wp-admin/admin-ajax.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: 'action=get_lightbox_table'
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            const tableContainer = document.createElement('div');
            tableContainer.className = 'p table-container';
            
            const tableHTML = data.data.replace('<table', '<table class="textcols_one pdf-table zakladka zakladka_red"');
            tableContainer.innerHTML = tableHTML;
            
            const selection = window.getSelection();
            let insertPosition = editor;
            
            if (selection.rangeCount > 0) {
                const range = selection.getRangeAt(0);
                const currentNode = range.startContainer;
                
                const currentTableContainer = currentNode.closest ? 
                    currentNode.closest('.table-container') : 
                    currentNode.parentElement.closest('.table-container');
                
                if (currentTableContainer) {
                    insertPosition = currentTableContainer.parentNode;
                    insertPosition.insertBefore(tableContainer, currentTableContainer.nextSibling);
                } else {
                    const currentP = currentNode.closest ? 
                        currentNode.closest('.p') : 
                        currentNode.parentElement.closest('.p');
                    
                    if (currentP) {
                        insertPosition = currentP.parentNode;
                        insertPosition.insertBefore(tableContainer, currentP.nextSibling);
                    } else {
                        editor.appendChild(tableContainer);
                    }
                }
            } else {
                editor.appendChild(tableContainer);
            }
            
            createEmptyBlockAfterTable(tableContainer);
            setCursorToFirstTableCell(tableContainer);
            
            console.log('Table created successfully');
        } else {
            throw new Error('Failed to load table');
        }
    })
    .catch(error => {
        console.error('Error loading table:', error);
    });
}

function createEmptyBlockAfterTable(tableContainer) {
    const emptyBlock = document.createElement('div');
    emptyBlock.className = 'p';
    emptyBlock.innerHTML = '<br>';
    
    const parent = tableContainer.parentNode;
    parent.insertBefore(emptyBlock, tableContainer.nextSibling);
    
    return emptyBlock;
}

function isCursorInValidPosition() {
    const selection = window.getSelection();
    if (!selection.rangeCount) return false;
    
    const range = selection.getRangeAt(0);
    const currentNode = range.startContainer;
    
    const currentTable = currentNode.closest ? 
        currentNode.closest('.table-container') : 
        currentNode.parentElement.closest('.table-container');
    
    if (currentTable) {
        return false;
    }
    
    const currentP = currentNode.closest ? 
        currentNode.closest('.p') : 
        currentNode.parentElement.closest('.p')
        ;
    
    return !!currentP;
}

function showCursorAlert() {
    alert('Пожалуйста, поставьте курсор в пустом текстовом блоке , где должна быть расположена таблица.');
}

function handleDeleteTable() {
    deleteCurrentTable();
}

function deleteCurrentTable() {
    const selection = window.getSelection();
    if (!selection.rangeCount) {
        alert('Пожалуйста, установите курсор внутри таблицы с одной колонкой, которую хотите удалить.');
        return;
    }
    
    let tableContainer = null;
    
    if (selection.anchorNode) {
        const node = selection.anchorNode.nodeType === Node.TEXT_NODE ? 
            selection.anchorNode.parentElement : 
            selection.anchorNode;
        
        tableContainer = node.closest('.table-container');
        
        if (tableContainer && !tableContainer.querySelector('.textcols_one')) {
            tableContainer = null;
        }
    }
    
    if (tableContainer) {
        if (confirm('Если вы удалите таблицу, то из нее пропадут все данные. Продолжить?')) {
            // Сохраняем ссылки на соседние элементы
            const prevElement = tableContainer.previousElementSibling;
            const nextElement = tableContainer.nextElementSibling;
            
            // Удаляем таблицу
            tableContainer.remove();
            
            // Теперь обрабатываем оставшиеся .p блоки
            processRemainingPBlocks(prevElement, nextElement);
            
            console.log('Table deleted successfully');
        }
    } else {
        alert('Вы не находитесь внутри таблицы с одной колонкой. Пожалуйста, установите курсор внутри таблицы, которую хотите удалить.');
    }
}

function processRemainingPBlocks(prevElement, nextElement) {
    // Проверяем оба элемента на наличие .p и пустоту
    const prevIsEmptyP = prevElement && 
                        prevElement.classList.contains('p') && 
                        isEmptyPBlock(prevElement);
    
    const nextIsEmptyP = nextElement && 
                        nextElement.classList.contains('p') && 
                        isEmptyPBlock(nextElement);
    
    // Сценарий 1: Оба пустые .p блоки
    if (prevIsEmptyP && nextIsEmptyP) {
        // Удаляем следующий блок (оставляем предыдущий)
        nextElement.remove();
        
        // Активируем оставшийся блок
        if (!prevElement.innerHTML.trim()) {
            prevElement.innerHTML = '&#8203;';
            setTimeout(() => setCursorToEnd(prevElement), 0);
        }
    }
    // Сценарий 2: Только следующий пустой .p
    else if (nextIsEmptyP) {
        // Удаляем пустой блок (не оставляем два подряд)
        nextElement.remove();
        
        // Если предыдущий элемент тоже .p (но не пустой), активируем его
        if (prevElement && prevElement.classList.contains('p')) {
            setTimeout(() => setCursorToEnd(prevElement), 0);
        }
    }
    // Сценарий 3: Только предыдущий пустой .p
    else if (prevIsEmptyP) {
        // Активируем предыдущий блок
        if (!prevElement.innerHTML.trim()) {
            prevElement.innerHTML = '&#8203;';
            setTimeout(() => setCursorToEnd(prevElement), 0);
        }
    }
    // Сценарий 4: Нет пустых .p блоков
    else {
        // Ничего не делаем
    }
}

function setCursorToEnd(element) {
    if (!element) return;
    
    const range = document.createRange();
    const selection = window.getSelection();
    
    // Если есть текстовый узел
    if (element.firstChild && element.firstChild.nodeType === Node.TEXT_NODE) {
        range.setStart(element.firstChild, element.firstChild.length);
    } else {
        // Иначе ставим в конец элемента
        range.selectNodeContents(element);
        range.collapse(false);
    }
    
    selection.removeAllRanges();
    selection.addRange(range);
    element.focus();
}

function isEmptyPBlock(pElement) {
    if (!pElement) return true;
    
    const html = pElement.innerHTML;
    
    // Проверяем на различные варианты "пустоты"
    const emptyPatterns = [
        '', 
        '<br>', 
        '<br/>', 
        '&ZeroWidthSpace;', 
        '&#8203;', 
        '​', // zero-width space как символ
        ' ', // обычный пробел
        '&nbsp;' // неразрывный пробел
    ];
    
    // Убираем все пробелы и проверяем
    const cleanHtml = html.replace(/\s/g, '')
                          .replace(/&nbsp;/g, '')
                          .replace(/&#8203;/g, '')
                          .replace(/&ZeroWidthSpace;/g, '')
                          .trim();
    
    return emptyPatterns.includes(html) || cleanHtml === '';
}
// Эта функция предотвращает создание двух пустых .p блоков подряд
function preventConsecutiveEmptyPBlocks() {
    const observer = new MutationObserver(function(mutations) {
        mutations.forEach(function(mutation) {
            if (mutation.type === 'childList') {
                // Ждем немного чтобы DOM обновился
                setTimeout(() => {
                    const allP = document.querySelectorAll('.p');
                    
                    for (let i = 0; i < allP.length - 1; i++) {
                        const current = allP[i];
                        const next = allP[i + 1];
                        
                        // Проверяем что они соседи и оба пустые
                        if (current.nextElementSibling === next &&
                            isEmptyPBlock(current) && 
                            isEmptyPBlock(next)) {
                            
                            // Удаляем второй блок
                            next.remove();
                            console.log('Removed duplicate empty .p block');
                            break;
                        }
                    }
                }, 10);
            }
        });
    });
    
    observer.observe(document.body, { childList: true, subtree: true });
}

// Инициализируйте при загрузке
document.addEventListener('DOMContentLoaded', preventConsecutiveEmptyPBlocks);

function setCursorToFirstTableCell(tableContainer) {
    const table = tableContainer.querySelector('table');
    if (!table) return;
    
    const firstRow = table.querySelector('tbody tr') || table.querySelector('tr');
    if (!firstRow) return;
    
    const firstCell = firstRow.querySelector('td') || firstRow.querySelector('th');
    if (!firstCell) return;
    
    const selection = window.getSelection();
    const range = document.createRange();
    
    range.setStart(firstCell, 0);
    range.setEnd(firstCell, 0);
    
    selection.removeAllRanges();
    selection.addRange(range);
    
    firstCell.focus();
}

function toggleZakladkaClassOne() {
    const selection = window.getSelection();
    if (!selection.rangeCount) {
        alert('Пожалуйста, установите курсор внутри таблицы с одной колонкой.');
        return;
    }
    
    let table = null;
    
    if (selection.anchorNode) {
        const node = selection.anchorNode.nodeType === Node.TEXT_NODE ? 
            selection.anchorNode.parentElement : 
            selection.anchorNode;
        
        table = node.closest('.textcols_one');
    }
    
    if (table) {
        if (table.classList.contains('zakladka')) {
            table.classList.remove('zakladka');
            console.log('Class zakladka removed from one-column table');
        } else {
            table.classList.add('zakladka');
            console.log('Class zakladka added to one-column table');
        }
    } else {
        alert('Вы не находитесь внутри таблицы с одной колонкой. Пожалуйста, установите курсор внутри таблицы.');
    }
}