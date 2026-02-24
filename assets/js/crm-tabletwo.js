// ==================== ТАБЛИЦА С ДВУМЯ КОЛОНКАМИ ====================

let tableTwoHandlersInitialized = false;

function initializeTableTwoHandlers() {
    if (tableTwoHandlersInitialized) {
        console.log('Table two handlers already initialized');
        return;
    }
    
    console.log('=== INITIALIZING TABLE TWO HANDLERS ===');
    
    // Единый обработчик для кнопок таблицы с двумя колонками
    document.addEventListener('click', function(e) {
        const target = e.target;
        const button = target.closest('.format-btn');
        
        if (!button) return;
        
        const buttonId = button.id;
        
        // ТОЛЬКО таблица с двумя колонками
        switch(buttonId) {
            case 'insertTableTwoBtn':
                e.preventDefault();
                e.stopPropagation();
                console.log('=== INSERT TABLE TWO CALLED ===');
                handleInsertTableTwo();
                break;
                
            case 'deleteTableTwoBtn':
                e.preventDefault();
                e.stopPropagation();
                console.log('=== DELETE TABLE TWO CALLED ===');
                handleDeleteTableTwo();
                break;
                
            case 'zakladkaBtn':
                e.preventDefault();
                e.stopPropagation();
                console.log('=== ZAKLADKA BUTTON TWO CALLED ===');
                toggleZakladkaClassTwo();
                break;
                
            case 'delete-own':
                e.preventDefault();
                e.stopPropagation();
                console.log('=== DELETE OWN CALLED ===');
                deleteCurrentTableCell();
                break;
        }
    });
    
    tableTwoHandlersInitialized = true;
    console.log('=== TABLE TWO HANDLERS INITIALIZED ===');
}

// Инициализация при загрузке
document.addEventListener('DOMContentLoaded', function() {
    initializeTableTwoHandlers();
});

// Функция для переинициализации
window.reinitializeTableTwoHandlers = function() {
    console.log('Reinitializing table two handlers');
    tableTwoHandlersInitialized = false;
    initializeTableTwoHandlers();
};

// ==================== ОСНОВНЫЕ ФУНКЦИИ ТАБЛИЦЫ С ДВУМЯ КОЛОНКАМИ ====================

function handleInsertTableTwo() {
    console.log('=== handleInsertTableTwo called ===');
    
    const selection = window.getSelection();
    if (selection.rangeCount > 0) {
        const node = selection.anchorNode.nodeType === Node.TEXT_NODE ? 
            selection.anchorNode.parentElement : 
            selection.anchorNode;
        
        // Если курсор в таблице с одной колонкой - преобразуем её
        if (node.closest('.textcols_one')) {
            convertOneToTwoColumns();
            return;
        }
    }
    
    // Иначе стандартная логика вставки новой таблицы
    if (!isCursorInValidPosition()) {
        showCursorAlert();
        return;
    }
    
    const editor = getActiveEditor();
    if (editor) {
        insertTableTwo(editor);
    }
}

function insertTableTwo(editor) {
    console.log('=== insertTableTwo called ===');
    
    fetch('/wp-admin/admin-ajax.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: 'action=get_lightbox_table_two_columns'
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            const tableContainer = document.createElement('div');
            tableContainer.className = 'p table-container table-two-columns';
            
            const tableHTML = data.data.replace('<table', '<table class="textcols_two textcols pdf-table"');
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
            setCursorToFirstTableCellTwo(tableContainer);
            
            console.log('Two-column table created successfully');
        } else {
            throw new Error('Failed to load two-column table');
        }
    })
    .catch(error => {
        console.error('Error loading two-column table:', error);
    });
}

function handleDeleteTableTwo() {
    deleteCurrentTableTwo();
}

function deleteCurrentTableTwo() {
    const selection = window.getSelection();
    if (!selection.rangeCount) {
        alert('Пожалуйста, установите курсор внутри таблицы с двумя колонками, которую хотите удалить.');
        return;
    }
    
    let tableContainer = null;
    
    if (selection.anchorNode) {
        const node = selection.anchorNode.nodeType === Node.TEXT_NODE ? 
            selection.anchorNode.parentElement : 
            selection.anchorNode;
        
        tableContainer = node.closest('.table-two-columns');
    }
    
    if (tableContainer) {
        if (confirm('Если вы удалите таблицу с двумя колонками, то из нее пропадут все данные. Продолжить?')) {
            // Сохраняем ссылки на соседние элементы
            const prevElement = tableContainer.previousElementSibling;
            const nextElement = tableContainer.nextElementSibling;
            
            // Удаляем таблицу
            tableContainer.remove();
            
            // Теперь обрабатываем оставшиеся .p блоки
            processRemainingPBlocks(prevElement, nextElement);
            
            console.log('Two-column table deleted successfully');
        }
    } else {
        alert('Вы не находитесь внутри таблицы с двумя колонками. Пожалуйста, установите курсор внутри таблицы, которую хотите удалить.');
    }
}

// Оставляем существующие функции без изменений:
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
        '​', 
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

function toggleZakladkaClassTwo() {
    const selection = window.getSelection();
    if (!selection.rangeCount) {
        alert('Пожалуйста, установите курсор внутри ячейки таблицы с двумя колонками.');
        return;
    }
    
    let tableCell = null;
    
    if (selection.anchorNode) {
        const node = selection.anchorNode.nodeType === Node.TEXT_NODE ? 
            selection.anchorNode.parentElement : 
            selection.anchorNode;
        
        tableCell = node.closest('.textcols-column');
    }
    
    if (tableCell) {
        if (tableCell.classList.contains('zakladka')) {
            tableCell.classList.remove('zakladka');
            console.log('Class zakladka removed from two-column table cell');
        } else {
            tableCell.classList.add('zakladka');
            console.log('Class zakladka added to two-column table cell');
        }
    } else {
        alert('Вы не находитесь внутри ячейки таблицы с двумя колонками. Пожалуйста, установите курсор внутри ячейки.');
    }
}

function deleteCurrentTableCell() {
    const selection = window.getSelection();
    if (!selection.rangeCount) {
        alert('Пожалуйста, установите курсор внутри ячейки таблицы, которую хотите очистить.');
        return;
    }
    
    let tableCell = null;
    
    if (selection.anchorNode) {
        const node = selection.anchorNode.nodeType === Node.TEXT_NODE ? 
            selection.anchorNode.parentElement : 
            selection.anchorNode;
        
        tableCell = node.closest('.textcols-column');
    }
    
    if (tableCell) {
        if (confirm('После удаления все данные в этой ячейке будут стерты, Продолжить?')) {
            const dateElement = tableCell.querySelector('.textcols_header .data');
            const savedDate = dateElement ? dateElement.innerHTML : '';
            
            const columnContent = tableCell.querySelector('.column-content');
            if (columnContent) {
                columnContent.innerHTML = `
                    <div class="column-content">
                <div class="textcols_header">
                    <div class="texttwo name two_color">
                        <p>Световой короб</p>
                    </div>
                    <div class="podtext name two_color">
                        <p>Подзаголовок</p>
                    </div>
                </div>
                <p class="texnik glav_color">Технические условия:</p>
                <p class="osnova glav_color">текст услуги</p>
            </div>
                `;
            }
            
            console.log('Table cell content cleared (except date)');
        }
    } else {
        alert('Вы не находитесь внутри ячейки таблицы. Пожалуйста, установите курсор внутри ячейки, которую хотите очистить.');
    }
}

function setCursorToFirstTableCellTwo(tableContainer) {
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

// Функции преобразования таблиц (заглушки)
function convertOneToTwoColumns() {
    const selection = window.getSelection();
    if (!selection.rangeCount) return;
    
    const node = selection.anchorNode.nodeType === Node.TEXT_NODE ? 
        selection.anchorNode.parentElement : 
        selection.anchorNode;
    
    const tableContainer = node.closest('.table-container');
    if (!tableContainer) return;
    
    alert('Пожалуйста, поставьте курсор в пустом текстовом блоке, где должна быть расположена таблица.');
}



// Общие вспомогательные функции (должны быть в основном файле таблиц)
function getActiveEditor() {
    const activeDialog = document.querySelector('.dialog-item.active');
    if (activeDialog) {
        return activeDialog.querySelector('.file-content-editor');
    }
    return document.querySelector('.file-content-editor');
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
        currentNode.parentElement.closest('.p');
    
    return !!currentP;
}

function showCursorAlert() {
    alert('Пожалуйста, поставьте курсор в пустом текстовом блоке, где должна быть расположена таблица.');
}

function createEmptyBlockAfterTable(tableContainer) {
    const emptyBlock = document.createElement('div');
    emptyBlock.className = 'p';
    emptyBlock.innerHTML = '<br>';
    
    const parent = tableContainer.parentNode;
    parent.insertBefore(emptyBlock, tableContainer.nextSibling);
    
    return emptyBlock;
}


