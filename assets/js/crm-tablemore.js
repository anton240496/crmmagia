// большая таблица
// Глобальная переменная для хранения позиции курсора
// ==================== БОЛЬШАЯ ТАБЛИЦА ====================

let tableMoreHandlersInitialized = false;

function initializeTableMoreHandlers() {
    if (tableMoreHandlersInitialized) {
        console.log('Table more handlers already initialized');
        return;
    }

    console.log('=== INITIALIZING TABLE MORE HANDLERS ===');

    // Единый обработчик для кнопок большой таблицы
    document.addEventListener('click', function (e) {
        const target = e.target;
        const button = target.closest('.format-btn');

        if (!button) return;

        const buttonId = button.id;

        // ТОЛЬКО большая таблица
        switch (buttonId) {
            case 'insertTablemoreBtn':
                e.preventDefault();
                e.stopPropagation();
                console.log('=== INSERT TABLE MORE CALLED ===');
                handleInsertTableMore();
                break;

            case 'deleteTablemoreBtn':
                e.preventDefault();
                e.stopPropagation();
                console.log('=== DELETE TABLE MORE CALLED ===');
                handleDeleteTableMore();
                break;

            case 'stroke_plus_st':
                e.preventDefault();
                e.stopPropagation();
                console.log('=== STROKE PLUS ST CALLED ===');
                addTableRow('Шт.', 'black');
                break;

            case 'stroke_plus_ysl':
                e.preventDefault();
                e.stopPropagation();
                console.log('=== STROKE PLUS YSL CALLED ===');
                addTableRow('Услуга', 'yellow');
                break;

            case 'stroke_minus':
                e.preventDefault();
                e.stopPropagation();
                console.log('=== STROKE MINUS CALLED ===');
                removeTableRow();
                break;

            case 'toggle_ysl':
                e.preventDefault();
                e.stopPropagation();
                console.log('=== TOGGLE YSL CALLED ===');
                toggleRowType();
                break;
        }
    });

    tableMoreHandlersInitialized = true;
    console.log('=== TABLE MORE HANDLERS INITIALIZED ===');
}



// Функция для переинициализации
window.reinitializeTableMoreHandlers = function () {
    console.log('Reinitializing table more handlers');
    tableMoreHandlersInitialized = false;
    initializeTableMoreHandlers();
};

function handleInsertTableMore() {
    console.log('=== handleInsertTableMore called ===');

    if (!isCursorInValidPosition()) {
        showCursorAlert();
        return;
    }

    const editor = getActiveEditor();
    if (editor) {
        insertTableMore(editor);
    }
}

function handleDeleteTableMore() {
    deleteCurrentTableMore();
}

function deleteCurrentTableMore() {
    const selection = window.getSelection();
    if (!selection.rangeCount) {
        alert('Пожалуйста, установите курсор внутри большой таблицы, которую хотите удалить.');
        return;
    }

    let tableContainer = null;

    if (selection.anchorNode) {
        const node = selection.anchorNode.nodeType === Node.TEXT_NODE ?
            selection.anchorNode.parentElement :
            selection.anchorNode;

        tableContainer = node.closest('.table-more-columns');
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

            console.log('More-column table deleted successfully');
        }
    } else {
        alert('Вы не находитесь внутри большой таблицы. Пожалуйста, установите курсор внутри таблицы, которую хотите удалить.');
    }
}

// Используем уже существующие вспомогательные функции:
// processRemainingPBlocks, setCursorToEnd, isEmptyPBlock

function isEmptyPBlock(pBlock) {
    // Получаем HTML содержимое блока
    const content = pBlock.innerHTML.trim();

    // Считаем блок пустым если:
    // - полностью пустой
    // - содержит только <br>
    // - содержит только пробелы и <br>
    // - содержит только &nbsp;
    return content === '' ||
        content === '<br>' ||
        content === '<br><br>' ||
        content.replace(/&nbsp;/g, '').replace(/\s/g, '').replace(/<br>/g, '') === '' ||
        content === '&nbsp;';
}

// Функция для отладки - показывает где сейчас курсор
function debugCursorPosition() {
    const selection = window.getSelection();
    if (!selection.rangeCount) {
        console.log('❌ Нет активного выделения');
        return null;
    }

    const range = selection.getRangeAt(0);
    console.log('📍 Курсор находится в:', {
        node: range.startContainer,
        nodeType: range.startContainer.nodeType,
        textContent: range.startContainer.textContent ? range.startContainer.textContent.substring(0, 50) : 'null',
        offset: range.startOffset
    });

    // Проверяем, находится ли курсор в таблице
    let currentNode = range.startContainer;
    if (currentNode.nodeType === Node.TEXT_NODE) {
        currentNode = currentNode.parentElement;
    }

    const table = currentNode.closest('table.textcols_more');
    const row = currentNode.closest('tr.tr_info:not(.tr_itog)');

    console.log('📊 Найдена таблица:', !!table);
    console.log('📝 Найдена строка данных:', !!row);

    return { table, row };
}
let lastCursorPosition = null;

// Функция для сохранения позиции курсора
function saveCursorPosition() {
    const selection = window.getSelection();
    if (!selection.rangeCount) return null;

    const range = selection.getRangeAt(0);
    lastCursorPosition = {
        startContainer: range.startContainer,
        startOffset: range.startOffset,
        endContainer: range.endContainer,
        endOffset: range.endOffset
    };

    return lastCursorPosition;
}

// Функция для восстановления позиции курсора
function restoreCursorPosition() {
    if (!lastCursorPosition) return;

    const selection = window.getSelection();
    const range = document.createRange();

    try {
        range.setStart(lastCursorPosition.startContainer, lastCursorPosition.startOffset);
        range.setEnd(lastCursorPosition.endContainer, lastCursorPosition.endOffset);

        selection.removeAllRanges();
        selection.addRange(range);
    } catch (error) {
        console.log('Не удалось восстановить позицию курсора');
    }
}

// Инициализация новых кнопок таблиц
// Обработчик для кнопки вставки большой таблицы
const insertTablemoreBtn = document.getElementById('insertTablemoreBtn');
if (insertTablemoreBtn) {
    insertTablemoreBtn.addEventListener('click', function () {
        if (!isCursorInValidPosition()) {
            showCursorAlert();
            return;
        }

        const editor = document.querySelector('[contenteditable="true"]');
        if (editor) {
            insertTableMore(editor);
        }
    });
}

// Обработчик для кнопки удаления большой таблицы
const deleteTablemoreBtn = document.getElementById('deleteTablemoreBtn');
if (deleteTablemoreBtn) {
    deleteTablemoreBtn.addEventListener('click', function () {
        deleteTableMore();
    });
}


//  функция для вставки большой таблицы
// 🔥 ДОБАВЬТЕ В НАЧАЛО ФАЙЛА
let isCreatingTable = false;



function insertTableMore(editor) {
    // 🔥 ЗАЩИТА ОТ ПОВТОРНОГО ВЫЗОВА
    if (isCreatingTable) {
        console.warn('⚠️ Таблица уже создается, пропускаем повторный вызов');
        return;
    }

    isCreatingTable = true;
    console.log('🟡 Начало создания таблицы...');

    fetch('/wp-admin/admin-ajax.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: 'action=get_lightbox_table_more_columns'
    })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const tableContainer = document.createElement('div');
                tableContainer.className = 'p table-container table-more-columns';

                let tableHTML = data.data.replace('<table', '<table class="textcols_more textcols pdf-table"');
                tableHTML = addRowButtonsToTableHTML(tableHTML);

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

                findOrCreateEmptyBlockAfterTable(tableContainer);

                // 🔥 ВРЕМЕННО ОТКЛЮЧАЕМ initAutoTableEditing ДЛЯ ТЕСТА
                // initAutoTableEditing(tableContainer);
                console.log('🟡 initAutoTableEditing временно отключен');

                const table = tableContainer.querySelector('table');
                if (table) {
                    initRowButtonsHandlers(table);
                }

                setCursorToFirstTableCellMore(tableContainer);

                console.log('✅ Большая таблица создана с кнопками управления строками');
            } else {
                throw new Error('Failed to load more-column table');
            }
        })
        .catch(error => {
            console.error('Error loading more-column table:', error);
        })
        .finally(() => {
            // 🔥 СБРАСЫВАЕМ ФЛАГ ДАЖЕ ПРИ ОШИБКЕ
            setTimeout(() => {
                isCreatingTable = false;
                console.log('🟢 Флаг создания таблицы сброшен');
            }, 500);
        });
}

// Функция для добавления кнопок управления в HTML таблицы
function addRowButtonsToTableHTML(tableHTML) {
    tableHTML = tableHTML.replace(
        '</th>\n            </tr>',
        '</th>\n                <th class="table_name">\n                    <div class="table_cont">Действия</div>\n                </th>\n            </tr>'
    );

    tableHTML = tableHTML.replace(
        /<tr class="tr_info black shtit_red">[\s\S]*?<\/tr>/g,
        function (match) {
            return match.replace(
                /(<td class="table_info">22 000<\/td>\s*<\/tr>)/,
                `$1\n                <td class="table_info actions-cell">\n                    <button class="row-btn add-below-st" type="button" title="Добавить строку Шт ниже">+Шт</button>\n                    <button class="row-btn add-below-ysl" type="button" title="Добавить строку Услуга ниже">+Усл</button>\n                    <button class="row-btn toggle-type" type="button" title="Сменить тип строки">🔄</button>\n                    <button class="row-btn delete-row" type="button" title="Удалить строку">❌</button>\n                </td>\n            </tr>`
            );
        }
    );

    tableHTML = tableHTML.replace(
        /<tr class="tr_info yellow yslnds_red">[\s\S]*?<\/tr>/g,
        function (match) {
            return match.replace(
                /(<td class="table_info">22 000<\/td>\s*<\/tr>)/,
                `$1\n                <td class="table_info actions-cell">\n                    <button class="row-btn add-below-st" type="button" title="Добавить строку Шт ниже">+Шт</button>\n                    <button class="row-btn add-below-ysl" type="button" title="Добавить строку Услуга ниже">+Усл</button>\n                    <button class="row-btn toggle-type" type="button" title="Сменить тип строки">🔄</button>\n                    <button class="row-btn delete-row" type="button" title="Удалить строку">❌</button>\n                </td>\n            </tr>`
            );
        }
    );

    tableHTML = tableHTML.replace(
        /<tr class="tr_info tr_itog black shtit_red">[\s\S]*?<\/tr>/g,
        function (match) {
            return match.replace(
                /(<td class="table_info table_itog total-sum">44 000<\/td>\s*<\/tr>)/,
                `$1\n                <td class="table_info table_itog"></td>\n            </tr>`
            );
        }
    );

    tableHTML = tableHTML.replace(
        /<tr class="tr_info tr_itog yellow yslnds_red">[\s\S]*?<\/tr>/g,
        function (match) {
            return match.replace(
                /(<td class="table_info table_itog vat-amount">9 680<\/td>\s*<\/tr>)/,
                `$1\n                <td class="table_info table_itog"></td>\n            </tr>`
            );
        }
    );

    tableHTML = tableHTML.replace(
        /<tr class="tr_info tr_itog black shtit_red">[\s\S]*?<\/tr>/g,
        function (match) {
            return match.replace(
                /(<td class="table_info table_itog total-with-vat">53 680<\/td>\s*<\/tr>)/,
                `$1\n                <td class="table_info table_itog"></td>\n            </tr>`
            );
        }
    );

    return tableHTML;
}



// ==================== ФУНКЦИЯ 1: ВКЛЮЧЕНИЕ РЕДАКТИРОВАНИЯ ТАБЛИЦЫ ====================
function enableTableEditingForTable(table) {
    if (table.isEditing) return;

    const editableCells = table.querySelectorAll('.table_info.naim, .table_info:nth-child(4), .table_info:nth-child(5), .table_info:nth-child(6)');
    editableCells.forEach(cell => {
        cell.setAttribute('contenteditable', 'true');
    });

    const vatCell = table.querySelector('tr.tr_itog.yellow td.table_itog.name');
    if (vatCell) {
        vatCell.setAttribute('contenteditable', 'true');
    }

    const tableTitle = table.querySelector('.table_tit');
    if (tableTitle) {
        tableTitle.setAttribute('contenteditable', 'true');
        tableTitle.style.outline = 'none';
    }

    table.classList.add('editing-mode');
    showEditIndicator(table);
    table.isEditing = true;

    // Инициализируем обработчики для этой таблицы
    initTableHandlersForTable(table);

    // ДОБАВЛЯЕМ: Инициализируем предупреждения для конкретной таблицы
    if (typeof initSimpleWarningsForTable === 'function') {
        initSimpleWarningsForTable(table);
    }

    console.log('Table editing enabled (auto)');
}

// ==================== ФУНКЦИЯ 2: АВТОМАТИЧЕСКОЕ РЕДАКТИРОВАНИЕ ТАБЛИЦЫ ====================
function initAutoTableEditing(tableContainer) {
    const table = tableContainer.querySelector('table');
    if (!table) return;

    disableTableEditingForTable(table);

    table.addEventListener('click', function (e) {
        const targetCell = e.target.closest('.table_info') || e.target.closest('.table_itog') || e.target.closest('.table_tit');

        if (targetCell) {
            if (!table.isEditing) {
                enableTableEditingForTable(table);

                setTimeout(() => {
                    if (targetCell.hasAttribute('contenteditable')) {
                        targetCell.focus();
                    }
                }, 10);
            } else {
                if (targetCell.hasAttribute('contenteditable')) {
                    targetCell.focus();
                }
            }
        }
    });

    table.addEventListener('focusout', function (e) {
        setTimeout(() => {
            const activeElement = document.activeElement;
            if (!table.contains(activeElement)) {
                disableTableEditingForTable(table);
            }
        }, 50);
    });

    table.style.cursor = 'pointer';

    const allCells = table.querySelectorAll('.table_info, .table_itog, .table_tit');
    allCells.forEach(cell => {
        cell.style.cursor = 'pointer';
        cell.style.outline = 'none';
    });

    // ДОБАВЛЯЕМ: Инициализируем предупреждения для этой таблицы
    if (typeof initSimpleWarningsForTable === 'function') {
        initSimpleWarningsForTable(table);
    }
}


// Обработчики для кнопок в строках таблицы
function initRowButtonsHandlers(table) {
    table.addEventListener('click', function (e) {
        if (e.target.classList.contains('add-below-st')) {
            e.preventDefault();
            e.stopPropagation();
            const row = e.target.closest('tr');
            addRowBelow(row, 'Шт.', 'black shtit_red');
        }
    });

    table.addEventListener('click', function (e) {
        if (e.target.classList.contains('add-below-ysl')) {
            e.preventDefault();
            e.stopPropagation();
            const row = e.target.closest('tr');
            addRowBelow(row, 'Услуга', 'yellow yslnds_red');
        }
    });

    table.addEventListener('click', function (e) {
        if (e.target.classList.contains('toggle-type')) {
            e.preventDefault();
            e.stopPropagation();
            const row = e.target.closest('tr');
            toggleRowTypeDirect(row);
        }
    });

    table.addEventListener('click', function (e) {
        if (e.target.classList.contains('delete-row')) {
            e.preventDefault();
            e.stopPropagation();
            const row = e.target.closest('tr');
            deleteRowDirect(row);
        }
    });
}

function findOrCreateEmptyBlockAfterTable(tableContainer) {
    let nextElement = tableContainer.nextElementSibling;

    if (nextElement &&
        nextElement.classList.contains('p') &&
        isEmptyPBlock(nextElement)) {
        return nextElement;
    }

    const emptyBlock = document.createElement('div');
    emptyBlock.className = 'p';
    emptyBlock.innerHTML = '<br>';

    const parent = tableContainer.parentNode;
    parent.insertBefore(emptyBlock, tableContainer.nextSibling);

    return emptyBlock;
}

// Функция для добавления строки ниже указанной
function addRowBelow(currentRow, unitType, rowClass) {
    const table = currentRow.closest('table');
    const tbody = table.querySelector('tbody');

    const infoRows = tbody.querySelectorAll('tr.tr_info:not(.tr_itog)');
    const newRowNumber = infoRows.length + 1;

    const newRow = document.createElement('tr');
    newRow.className = `tr_info ${rowClass}`;
    newRow.innerHTML = `
        <td class="table_info">${newRowNumber}</td>
        <td class="table_info naim" contenteditable="true">
            <p>Световая вывеска, круглой формы. D - 700 мм.</p>
            <p>Глубина 80 мм.</p>
        </td>
        <td class="table_info">${unitType}</td>
        <td class="table_info" contenteditable="true">2</td>
        <td class="table_info" contenteditable="true">11 000</td>
        <td class="table_info" contenteditable="true">22 000</td>
        <td class="table_info actions-cell">
            <button class="row-btn add-below-st" type="button" title="Добавить строку Шт ниже">+Шт</button>
            <button class="row-btn add-below-ysl" type="button" title="Добавить строку Услуга ниже">+Усл</button>
            <button class="row-btn toggle-type" type="button" title="Сменить тип строки">усл/шт<br>шт/усл</button>
            <button class="row-btn delete-row" type="button" title="Удалить строку">-1</button>
        </td>
    `;

    tbody.insertBefore(newRow, currentRow.nextSibling);

    updateRowNumbers(table);
    recalculateTotals(table);
    showSuccessMessage(`Добавлена строка (${unitType})`);
    initNumericMasksForTable(table);
}
// Функция для смены типа строки
function toggleRowTypeDirect(row) {
    const unitCell = row.querySelector('td.table_info:nth-child(3)');
    const currentType = unitCell.textContent.trim();

    if (currentType === 'Шт.') {
        unitCell.textContent = 'Услуга';
        row.classList.remove('black');
        row.classList.remove('shtit_red');
        row.classList.add('yellow');
        row.classList.add('yslnds_red');
        showSuccessMessage('Изменено на "Услуга"');
    } else if (currentType === 'Услуга') {
        unitCell.textContent = 'Шт.';
        row.classList.remove('yellow');
        row.classList.remove('yslnds_red');
        row.classList.add('black');
        row.classList.add('shtit_red');
        showSuccessMessage('Изменено на "Шт."');
    }
}

// Функция для удаления строки
function deleteRowDirect(row) {
    const table = row.closest('table');
    const infoRows = table.querySelectorAll('tr.tr_info:not(.tr_itog)');

    if (infoRows.length <= 1) {
        alert('Нельзя удалить последнюю строку');
        return;
    }

    if (confirm('Удалить эту строку?')) {
        row.remove();
        updateRowNumbers(table);
        recalculateTotals(table);
        showSuccessMessage('Строка удалена');
    }
}


// ==================== ФУНКЦИЯ 4: ПОКАЗ ИНДИКАТОРА РЕДАКТИРОВАНИЯ ====================
// Эта функция показывает временный зеленый индикатор, что редактирование включено
function showEditIndicator(table) {
    // Создаем элемент индикатора
    const indicator = document.createElement('div');
    indicator.innerHTML = '✓ Редактирование включено';
    indicator.className = 'none';
    indicator.style.cssText = `
        position: absolute;
        top: 10px;
        right: 10px;
        background: #28a745;
        color: white;
        padding: 5px 10px;
        border-radius: 4px;
        font-size: 12px;
        font-weight: bold;
        z-index: 1000;
        opacity: 0.9;
        transition: opacity 0.3s ease;
    `;

    // Находим контейнер таблицы и добавляем индикатор
    const tableContainer = table.closest('.pdf-table');
    if (tableContainer) {
        tableContainer.style.position = 'relative';
        tableContainer.appendChild(indicator);

        // Автоматически убираем индикатор через 3 секунды
        setTimeout(() => {
            indicator.style.opacity = '0';
            setTimeout(() => {
                if (indicator.parentNode) {
                    indicator.remove();
                }
            }, 300);
        }, 3000);
    }
}

// ==================== ФУНКЦИЯ 5: ИНИЦИАЛИЗАЦИЯ ОБРАБОТЧИКОВ ДЛЯ ТАБЛИЦЫ ====================
function initTableHandlersForTable(table) {
    initNumericMasksForTable(table);
    makeTotalsNonEditableForTable(table);
    recalculateTotalsForTable(table);
    initRowButtonsHandlers(table);

    // ДОБАВЛЯЕМ: Инициализируем предупреждения для этой таблицы
    if (typeof initSimpleWarningsForTable === 'function') {
        initSimpleWarningsForTable(table);
    }

    console.log('Table handlers initialized for specific table');
}
// ==================== ФУНКЦИЯ ВЫКЛЮЧЕНИЕ РЕДАКТИРОВАНИЯ ТАБЛИЦЫ ====================
// Эта функция отключает режим редактирования таблицы - делает все ячейки нередактируемыми
// и убирает визуальные индикаторы
function disableTableEditingForTable(table) {
    // Если таблица не в режиме редактирования, выходим
    if (!table.isEditing) return;

    // Находим все редактируемые ячейки и убираем contenteditable
    const allCells = table.querySelectorAll('[contenteditable="true"]');
    allCells.forEach(cell => {
        cell.removeAttribute('contenteditable');
        cell.style.outline = 'none'; // Убираем outline
    });

    // Убираем редактирование с заголовка таблицы
    const tableTitle = table.querySelector('.table_tit');
    if (tableTitle) {
        tableTitle.removeAttribute('contenteditable');
        tableTitle.style.outline = 'none';
    }

    // Убираем визуальные индикаторы редактирования
    table.classList.remove('editing-mode');
    table.style.outline = 'none';
    table.style.backgroundColor = '';
    table.style.cursor = 'pointer';

    // Убираем зеленый индикатор "Редактирование включено"
    const tableContainer = table.closest('.pdf-table');
    if (tableContainer) {
        const existingIndicator = tableContainer.querySelector('div');
        if (existingIndicator && existingIndicator.style.background === '#28a745') {
            existingIndicator.remove();
        }
    }

    table.isEditing = false;

    console.log('Table editing disabled (auto)');
}





// Функция для удаления большой таблицы
function deleteTableMore() {
    const selection = window.getSelection();
    if (!selection.rangeCount) {
        alert('Пожалуйста, установите курсор внутри большой таблицы, которую хотите удалить.');
        return;
    }

    // Находим контейнер большой таблицы, в котором находится курсор
    let tableContainer = null;

    if (selection.anchorNode) {
        const node = selection.anchorNode.nodeType === Node.TEXT_NODE ?
            selection.anchorNode.parentElement :
            selection.anchorNode;

        // Ищем любую таблицу (не только table-more-columns)
        tableContainer = node.closest('.table-container');

        // Уточняем, что это именно большая таблица
        if (tableContainer && !tableContainer.querySelector('.textcols_more')) {
            tableContainer = null;
        }
    }

    // Проверяем, что нашли именно контейнер большой таблицы
    if (tableContainer) {
        // Показываем предупреждение
        if (confirm('Если вы удалите таблицу, то из нее пропадут все данные. Продолжить?')) {
            // Находим следующий блок p после таблицы
            const nextPBlock = tableContainer.nextElementSibling;

            // Проверяем, является ли следующий блок p и он пустой
            if (nextPBlock &&
                nextPBlock.classList.contains('p') &&
                isEmptyPBlock(nextPBlock)) {

                // Удаляем и таблицу и пустой блок p
                tableContainer.remove();
                nextPBlock.remove();
                console.log('More-column table container and empty p block deleted');
            } else {
                // Удаляем только таблицу
                tableContainer.remove();
                console.log('More-column table container deleted');
            }
        }
    } else {
        alert('Вы не находитесь внутри большой таблицы. Пожалуйста, установите курсор внутри таблицы, которую хотите удалить.');
    }
}


// Вспомогательная функция для установки курсора в первую ячейку большой таблицы
function setCursorToFirstTableCellMore(tableContainer) {
    const table = tableContainer.querySelector('table');
    if (!table) return;

    // Автоматически включаем редактирование при установке курсора
    enableTableEditingForTable(table);

    // Находим первую редактируемую ячейку
    const firstEditableCell = table.querySelector('.table_info.naim');
    if (!firstEditableCell) return;

    const selection = window.getSelection();
    const range = document.createRange();

    // Устанавливаем курсор в КОНЕЦ первой ячейки, а не в начало
    range.selectNodeContents(firstEditableCell);
    range.collapse(false); // false = в конец, true = в начало

    selection.removeAllRanges();
    selection.addRange(range);

    // Фокусируем ячейку
    firstEditableCell.focus();

    console.log('Cursor set to first table cell with auto-editing enabled');
}

// Вспомогательная функция для проверки, пустой ли блок p (уже должна быть в коде)

// Вспомогательная функция для активации таблицы
function activateTableForEditing(table) {
    if (!table) return null;

    // Включаем редактирование таблицы
    if (!table.isEditing) {
        enableTableEditingForTable(table);
    }

    return table;
}


// Вспомогательная функция для нахождения активной таблицы
// Простая функция для нахождения активной таблицы
function findActiveTable() {
    const selection = window.getSelection();
    if (!selection.rangeCount) {
        console.log('❌ Нет выделения');
        return null;
    }

    const range = selection.getRangeAt(0);
    let node = range.startContainer;

    // Если это текстовый узел, переходим к родителю
    if (node.nodeType === Node.TEXT_NODE) {
        node = node.parentElement;
    }

    // Ищем таблицу
    const table = node.closest('table.textcols_more');
    console.log('🔍 Поиск таблицы:', !!table);

    return table;
}



// Функция для показа успешного сообщения
function showSuccessMessage(message) {
    const successDiv = document.createElement('div');
    successDiv.textContent = message;
    successDiv.style.cssText = `
        position: fixed;
        top: 20px;
        right: 20px;
        background: #28a745;
        color: white;
        padding: 10px 15px;
        border-radius: 4px;
        font-size: 14px;
        font-weight: bold;
        z-index: 10000;
        opacity: 0.9;
    `;

    document.body.appendChild(successDiv);

    setTimeout(() => {
        successDiv.style.opacity = '0';
        setTimeout(() => {
            if (successDiv.parentNode) {
                successDiv.remove();
            }
        }, 300);
    }, 3000);
}

// Вспомогательная функция для нахождения активной строки
function findActiveRow(table) {
    if (!table) return null;

    // Пробуем найти по сохраненной позиции курсора
    if (lastCursorPosition) {
        try {
            const startNode = lastCursorPosition.startContainer;
            if (startNode) {
                const rowFromCursor = startNode.nodeType === Node.TEXT_NODE
                    ? startNode.parentElement.closest('tr.tr_info:not(.tr_itog)')
                    : startNode.closest('tr.tr_info:not(.tr_itog)');

                if (rowFromCursor && table.contains(rowFromCursor)) {
                    return rowFromCursor;
                }
            }
        } catch (error) {
            console.log('Не удалось найти строку по сохраненной позиции');
        }
    }

    // Если не нашли по сохраненной позиции, ищем по текущему selection
    const selection = window.getSelection();
    if (selection.rangeCount > 0) {
        let currentNode = selection.anchorNode;
        if (currentNode && currentNode.nodeType === Node.TEXT_NODE) {
            currentNode = currentNode.parentElement;
        }

        if (currentNode) {
            const row = currentNode.closest('tr.tr_info:not(.tr_itog)');
            if (row && table.contains(row)) {
                return row;
            }
        }
    }

    // Если не нашли, берем первую строку с данными
    const infoRows = table.querySelectorAll('tr.tr_info:not(.tr_itog)');
    if (infoRows.length > 0) {
        return infoRows[0];
    }

    return null;
}


// Функция для добавления новой строки в таблицу
function addTableRow(unitType, rowClass) {
    console.log(`🟢 ЗАПУСК addTableRow: ${unitType}`);

    // Показываем отладку
    const debug = debugCursorPosition();

    const table = findActiveTable();
    console.log('📋 Найдена таблица:', table);

    if (!table) {
        alert('❌ Пожалуйста, сначала создайте таблицу.');
        return;
    }

    const currentRow = findActiveRow(table);
    console.log('📝 Найдена строка для вставки:', currentRow);

    if (!currentRow) {
        alert('❌ Пожалуйста, установите курсор внутри строки таблицы, после которой нужно добавить новую строку.');
        return;
    }

    // Активируем таблицу для редактирования
    table = activateTableForEditing(table);

    // Находим tbody таблицы
    const tbody = table.querySelector('tbody');
    if (!tbody) {
        console.error('❌ Не найден tbody в таблице');
        return;
    }

    // Находим все строки с классом tr_info (данные)
    const infoRows = tbody.querySelectorAll('tr.tr_info:not(.tr_itog)');
    console.log('🔢 Всего строк данных:', infoRows.length);

    if (infoRows.length === 0) {
        console.error('❌ Не найдены строки для копирования');
        return;
    }

    // Определяем индекс для новой строки
    const newRowNumber = infoRows.length + 1;

    // Создаем новую строку на основе шаблона
    const newRow = document.createElement('tr');
    newRow.className = `tr_info ${rowClass}`;

    // HTML для новой строки БЕЗ contenteditable
    newRow.innerHTML = `
        <td class="table_info">${newRowNumber}</td>
        <td class="table_info naim">
            <p>Световая вывеска, круглой формы. D - 700 мм.</p>
            <p>Глубина 80 мм.</p>
        </td>
        <td class="table_info">${unitType}</td>
        <td class="table_info">2</td>
        <td class="table_info">11 000</td>
        <td class="table_info">22 000</td>
    `;

    // Вставляем новую строку ПОСЛЕ строки с курсором
    tbody.insertBefore(newRow, currentRow.nextSibling);
    console.log('✅ Новая строка добавлена после текущей');

    // Обновляем номера строк
    updateRowNumbers(table);

    // Пересчитываем итоги после добавления строки
    recalculateTotals(table);

    // 🔥 ДОБАВЬТЕ ЭТУ СТРОКУ:
    initNumericMasksForTable(table); // Инициализировать маски для новой строки

    // Показываем уведомление об успешном добавлении
    showSuccessMessage(`✅ Добавлена новая строка (${unitType})`);

    console.log(`✅ addTableRow завершена: ${unitType}`);
}



// Функция для смены типа строки
function toggleRowType() {
    const table = findActiveTable();
    if (!table) {
        alert('Пожалуйста, сначала создайте таблицу.');
        return;
    }

    const currentRow = findActiveRow(table);
    if (!currentRow) {
        alert('Пожалуйста, установите курсор внутри строки таблицы.');
        return;
    }


}

// Функция для удаления строки из таблицы
function removeTableRow() {
    // Находим активную таблицу
    let table = findActiveTable();

    // Если таблица не найдена, ищем любую таблицу на странице
    if (!table) {
        const tables = document.querySelectorAll('table.textcols_more');
        if (tables.length > 0) {
            table = tables[0]; // Берем первую таблицу
        }
    }

    if (!table) {
        alert('Пожалуйста, сначала создайте таблицу или установите курсор внутри таблицы.');
        return;
    }

    // Активируем таблицу для редактирования
    table = activateTableForEditing(table);

    // Находим строку ДЛЯ УДАЛЕНИЯ - именно ту, где курсор
    let currentRow = null;
    const selection = window.getSelection();

    if (selection.rangeCount > 0) {
        let currentNode = selection.anchorNode;
        if (currentNode.nodeType === Node.TEXT_NODE) {
            currentNode = currentNode.parentElement;
        }
        currentRow = currentNode.closest('tr.tr_info:not(.tr_itog)');
    }

    if (!currentRow) {
        alert('Пожалуйста, установите курсор внутри строки таблицы, которую хотите удалить.');
        return;
    }

    // Проверяем, что это не итоговая строка
    if (currentRow.classList.contains('tr_itog')) {
        alert('Для удаления строки пожалуйста, установите курсор на строку с данными, а не на итоговую строку.');
        return;
    }

    // Проверяем, что осталась хотя бы одна строка данных (не считая итоговых)
    const infoRows = table.querySelectorAll('tr.tr_info:not(.tr_itog)');
    if (infoRows.length <= 1) {
        alert('Нельзя удалить последнюю строку с данными в таблице.');
        return;
    }

    // Показываем предупреждение
    if (confirm('Вы уверены, что хотите удалить эту строку? Данные будут потеряны.')) {
        // Удаляем строку ГДЕ КУРСОР
        currentRow.remove();

        // Обновляем номера строк
        updateRowNumbers(table);

        // Пересчитываем итоги после удаления строки
        recalculateTotals(table);

        // Показываем уведомление об успешном удалении
        showSuccessMessage('Строка удалена из таблицы');

        console.log('Строка удалена из таблицы');
    }
}

// Функция для обновления номеров строк
// ==================== ИНИЦИАЛИЗАЦИЯ ПРИ ЗАГРУЗКЕ СТРАНИЦЫ ====================
// ПЕРЕМЕЩАЕМ ЭТОТ БЛОК В САМЫЙ КОНЕЦ ФАЙЛА, ПОСЛЕ ВСЕХ ОПРЕДЕЛЕНИЙ ФУНКЦИЙ

// Функция для обновления номеров строк
function updateRowNumbers(table) {
    const infoRows = table.querySelectorAll('tr.tr_info:not(.tr_itog)');

    infoRows.forEach((row, index) => {
        const numberCell = row.querySelector('td.table_info:first-child');
        if (numberCell) {
            numberCell.textContent = index + 1;
        }
    });
}

// ==================== ГЛОБАЛЬНАЯ ИНИЦИАЛИЗАЦИЯ РЕДАКТИРОВАНИЯ ТАБЛИЦ ====================
function initGlobalTableEditing() {
    const tables = document.querySelectorAll('.textcols_more');

    tables.forEach(table => {
        // Изначально таблица не редактируема
        disableTableEditingForTable(table);

        // Обработчик клика на таблице (вместо focusin)
        table.addEventListener('click', function (e) {
            if (e.target.closest('.table_info') || e.target.closest('.table_itog')) {
                if (!table.isEditing) {
                    enableTableEditingForTable(table);
                    // После включения редактирования фокусируемся на ячейке
                    setTimeout(() => {
                        const targetCell = e.target.closest('.table_info, .table_itog');
                        if (targetCell && targetCell.hasAttribute('contenteditable')) {
                            targetCell.focus();
                        }
                    }, 10);
                }
            }
        });

        // Обработчик потери фокуса с таблицы
        table.addEventListener('focusout', function (e) {
            setTimeout(() => {
                const activeElement = document.activeElement;
                // Если фокус ушел с таблицы полностью
                if (!table.contains(activeElement)) {
                    disableTableEditingForTable(table);
                }
            }, 50);
        });

        // Визуальные индикаторы для нередактируемой таблицы
        table.style.cursor = 'pointer';
        table.title = 'Кликните для редактирования таблицы';

        // Делаем все ячейки нередактируемыми изначально
        const allCells = table.querySelectorAll('.table_info, .table_itog');
        allCells.forEach(cell => {
            cell.style.cursor = 'pointer';
        });
    });
}

// ==================== ИНИЦИАЛИЗАЦИЯ ОСНОВНЫХ ОБРАБОТЧИКОВ ====================
function initTableHandlers() {
    // Добавляем обработчики
    document.addEventListener('input', handleInputEvent);
    document.addEventListener('blur', handleBlurEvent);

    // Инициализируем маски для всех редактируемых таблиц
    initNumericMasks();
    makeTotalsNonEditable();
    recalculateTotals();
}

// ==================== ЗАПУСК ПРИ ЗАГРУЗКЕ СТРАНИЦЫ ====================
document.addEventListener('DOMContentLoaded', function () {
    setTimeout(() => {
        initTableHandlers();
        initGlobalTableEditing(); // Инициализируем автоматическое редактирование

        // ДОБАВЛЯЕМ: Инициализируем глобальные предупреждения
        if (typeof initSimpleWarnings === 'function') {
            initSimpleWarnings();
        }

        // Инициализируем обработчики для большой таблицы
        initializeTableMoreHandlers();
    }, 100);
});