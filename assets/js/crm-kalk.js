// калькулятор - НДС РАБОТАЕТ КАК ОБЫЧНЫЕ ЧИСЛА
let tableChangeTimeout;
let isTableEditing = false;


// Функция 2: Предупреждение при клике на заголовки столбцов
function showColumnWarning() {
    console.log('🔔 showColumnWarning вызвана');

    const warningDiv = document.createElement('div');
    warningDiv.innerHTML = `
        <div style="position: fixed; top: 50%; right: 5%; transform: translate(-50%, -50%); 
                    background: #f8d7da; border: 2px solid #dc3545; padding: 10px; border-radius: 8px; 
                    font-size: 14px; z-index: 10000; box-shadow: 0 4px 12px rgba(0,0,0,0.3); max-width: 250px;" class="none">
            <strong>❌ Нельзя редактировать</strong><br>
            Названия столбцов
        </div>
    `;

    document.body.appendChild(warningDiv);
    console.log('🔔 Уведомление добавлено в DOM');

    // Убираем через 2 секунды
    setTimeout(() => {
        console.log('🔔 Начинаем скрывать уведомление');
        warningDiv.style.opacity = '0';
        warningDiv.style.transition = 'opacity 0.3s ease';
        setTimeout(() => {
            if (warningDiv.parentNode) {
                warningDiv.remove();
                console.log('🔔 Уведомление удалено');
            }
        }, 300);
    }, 2000);
}

// Функция для показа предупреждения для первого столбца (пункты)
function showFirstColumnWarning() {
    console.log('🔔 showFirstColumnWarning вызвана');

    const warningDiv = document.createElement('div');
    warningDiv.innerHTML = `
        <div style="position: fixed; top: 40%; right: 5%; transform: translate(-50%, -50%); 
                    background: #e2e3e5; border: 2px solid #6c757d; padding: 10px; border-radius: 8px; 
                    font-size: 14px; z-index: 10000; box-shadow: 0 4px 12px rgba(0,0,0,0.3); max-width: 300px;" class="none">
            <strong>Пункты выставляются автоматически, по очереди</strong>
        </div>
    `;

    document.body.appendChild(warningDiv);
    console.log('🔔 Уведомление добавлено в DOM');

    // Убираем через 2.5 секунды
    setTimeout(() => {
        console.log('🔔 Начинаем скрывать уведомление');
        warningDiv.style.opacity = '0';
        warningDiv.style.transition = 'opacity 0.3s ease';
        setTimeout(() => {
            if (warningDiv.parentNode) {
                warningDiv.remove();
                console.log('🔔 Уведомление удалено');
            }
        }, 300);
    }, 2500);
}



function showUnitWarning() {
    console.log('🔔 showUnitWarning вызвана');

    const warningDiv = document.createElement('div');
    warningDiv.innerHTML = `
        <div style="position: fixed; top: 40%; right: 5%; transform: translate(-50%, -50%); 
                    background: #fff3cd; border: 2px solid #ffc107; padding: 10px; border-radius: 8px; 
                    font-size: 14px; z-index: 10000; box-shadow: 0 4px 12px rgba(0,0,0,0.3); max-width: 300px;">
            <strong>кнопка "усл/шт шт/усл"</strong><br>
            на строке справа
        </div>
    `;

    document.body.appendChild(warningDiv);
    console.log('🔔 Уведомление добавлено в DOM');

    // Убираем через 2.5 секунды
    setTimeout(() => {
        console.log('🔔 Начинаем скрывать уведомление');
        warningDiv.style.opacity = '0';
        warningDiv.style.transition = 'opacity 0.3s ease';
        setTimeout(() => {
            if (warningDiv.parentNode) {
                warningDiv.remove();
                console.log('🔔 Уведомление удалено');
            }
        }, 300);
    }, 2500);
}
// Функция 3: Инициализация простых обработчиков
function initSimpleWarnings() {
    // Обработчик для единиц измерения (3-я ячейка)
    document.addEventListener('click', function (e) {
        const cell = e.target.closest('td.table_info:nth-child(3)');
        if (cell && cell.closest('table.textcols_more')) {
            e.preventDefault();
            e.stopPropagation();
            showUnitWarning();
        }
    });

    // Обработчик для первого столбца (пункты) - ИСКЛЮЧАЕМ итоговые ячейки и НДС
    document.addEventListener('click', function (e) {
        const firstColumnCell = e.target.closest('td.table_info:first-child');
        if (firstColumnCell && firstColumnCell.closest('table.textcols_more')) {
            // Проверяем, что это НЕ итоговая ячейка и НЕ ячейка НДС
            const row = firstColumnCell.closest('tr');
            if (!row.classList.contains('tr_itog') &&
                !firstColumnCell.classList.contains('name')) {
                e.preventDefault();
                e.stopPropagation();
                showFirstColumnWarning();
            }
        }
    });

    // Обработчик для заголовков столбцов (ИСКЛЮЧАЕМ заголовок таблицы)
    document.addEventListener('click', function (e) {
        // Показываем уведомление только для th.table_name, но не для .table_tit
        if (e.target.closest('th.table_name') && !e.target.closest('.table_tit')) {
            e.preventDefault();
            e.stopPropagation();
            showColumnWarning();
        }
    });
}

function initSimpleWarningsForTable(table) {
    // Единицы измерения (3-я ячейка)
    const unitCells = table.querySelectorAll('td.table_info:nth-child(3)');
    unitCells.forEach(cell => {
        cell.addEventListener('click', function (e) {
            e.preventDefault();
            e.stopPropagation();
            showUnitWarning();
        });
    });

    // Первый столбец (пункты) - ИСКЛЮЧАЕМ итоговые ячейки и НДС
    const firstColumnCells = table.querySelectorAll('td.table_info:first-child');
    firstColumnCells.forEach(cell => {
        cell.addEventListener('click', function (e) {
            // Проверяем, что это НЕ итоговая ячейка и НЕ ячейка НДС
            const row = cell.closest('tr');
            if (!row.classList.contains('tr_itog') &&
                !cell.classList.contains('name')) {
                e.preventDefault();
                e.stopPropagation();
                showFirstColumnWarning();
            }
        });
    });

    // Заголовки столбцов (ИСКЛЮЧАЕМ заголовок таблицы)
    const headers = table.querySelectorAll('th.table_name');
    headers.forEach(header => {
        header.addEventListener('click', function (e) {
            e.preventDefault();
            e.stopPropagation();
            showColumnWarning();
        });
    });
}

// ==================== УПРАВЛЕНИЕ РЕДАКТИРОВАНИЕМ ТАБЛИЦЫ ====================



// Глобальная инициализация автоматического редактирования для всех таблиц
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

// ==================== ФУНКЦИЯ СОХРАНЕНИЯ ПОЗИЦИИ КУРСОРА ====================
function saveCursorPosition(cell) {
    const selection = window.getSelection();
    if (selection.rangeCount === 0) return 0;

    const range = selection.getRangeAt(0);
    const preCaretRange = document.createRange();
    preCaretRange.selectNodeContents(cell);
    preCaretRange.setEnd(range.endContainer, range.endOffset);

    return preCaretRange.toString().length;
}

// ==================== ФУНКЦИЯ 2: ИЗВЛЕЧЕНИЕ ТОЛЬКО ЦИФР ====================
function extractNumbersOnly(text) {
    if (!text) return '';
    return text.replace(/\D/g, '');
}

// ==================== ФУНКЦИЯ 3: РАСЧЕТ НОВОЙ ПОЗИЦИИ КУРСОРА ====================
function calculateNewCursorPosition(originalPosition, textBeforeCursor, formattedText) {
    if (originalPosition <= 0) return 0;
    if (!formattedText) return 0;

    // Считаем сколько цифр было до курсора
    const digitsBeforeCursor = textBeforeCursor.replace(/\D/g, '').length;

    // В отформатированном тексте ищем позицию после N-й цифры
    let digitCount = 0;

    for (let i = 0; i < formattedText.length; i++) {
        if (formattedText[i] !== ' ') {
            digitCount++;
        }

        if (digitCount === digitsBeforeCursor) {
            return i + 1;
        }
    }

    return formattedText.length;
}

// ==================== ФУНКЦИЯ ВОССТАНОВЛЕНИЯ ПОЗИЦИИ КУРСОРА ====================
function restoreCursorPosition(cell, position) {
    const selection = window.getSelection();
    const range = document.createRange();

    if (cell.firstChild) {
        const textNode = cell.firstChild;
        const safePosition = Math.min(position, textNode.length);
        range.setStart(textNode, safePosition);
        range.collapse(true);
        selection.removeAllRanges();
        selection.addRange(range);
    }
}

// ==================== ФУНКЦИЯ 5: ПЛАНИРОВАНИЕ ПЕРЕСЧЕТА ====================
function scheduleRecalculation(cell) {
    clearTimeout(tableChangeTimeout);
    tableChangeTimeout = setTimeout(() => {
        const row = cell.closest('tr');
        if (row && !row.classList.contains('tr_itog') && !row.classList.contains('tr_name')) {
            const cellIndex = Array.from(cell.parentNode.cells).indexOf(cell);
            recalculateRow(row, cellIndex);
            recalculateTotals();
        }
    }, 300);
}

// ==================== МАСКИ ВВОДА ====================

// Функция для форматирования числа с пробелами


function formatNumberWithSpaces(num) {
    if (!num && num !== 0) return '';

    // Оставляем только цифры
    const numbers = num.toString().replace(/\D/g, '');
    if (numbers === '') return '';

    // Форматируем с пробелами
    return numbers.replace(/\B(?=(\d{3})+(?!\d))/g, ' ');
}

// Инициализация масок для числовых ячеек
// Обновленная функция для инициализации масок НДС
function initNumericMasks() {
    const numericCells = document.querySelectorAll('.table_info[contenteditable="true"]');

    numericCells.forEach(cell => {
        // Применяем маски только к колонкам 3,4,5 (количество, цена, сумма)
        const cellIndex = Array.from(cell.parentNode.cells).indexOf(cell);

        if (cellIndex === 3 || cellIndex === 4 || cellIndex === 5) {
            applyMaskToCell(cell);
        }
    });

    // Маска для ячейки НДС - используем улучшенную версию
    const vatCells = document.querySelectorAll('tr.tr_itog.yellow td.table_itog.name[contenteditable="true"]');
    vatCells.forEach(cell => {
        applyVatMaskToCell(cell);
    });
}

// Применение маски к числовой ячейке
function applyMaskToCell(cell) {
    const originalValue = cell.textContent.trim();

    // Форматируем начальное значение
    if (originalValue && originalValue !== '0') {
        const formatted = formatNumberWithSpaces(originalValue);
        if (formatted !== originalValue) {
            cell.textContent = formatted;
        }
    }

    // Обработчик ввода
    cell.addEventListener('input', function (e) {
        handleMaskedInput(this);
    });

    // Обработчик фокуса - очищаем если "0"
    cell.addEventListener('focus', function (e) {
        if (this.textContent === '0') {
            this.textContent = '';
        }
    });

    // Обработчик потери фокуса
    cell.addEventListener('blur', function (e) {
        if (!this.textContent || this.textContent.trim() === '') {
            this.textContent = '0';
        } else {
            // Форматируем значение при уходе
            const formatted = formatNumberWithSpaces(this.textContent);
            if (formatted !== this.textContent) {
                this.textContent = formatted;
            }
        }
    });

    // Запрет ввода нечисловых символов
    cell.addEventListener('keydown', function (e) {
        // Разрешаем: backspace, delete, tab, стрелки, Enter
        if ([8, 9, 13, 37, 38, 39, 40, 46].includes(e.keyCode)) {
            return;
        }

        // Разрешаем Ctrl+A, Ctrl+C, Ctrl+V, Ctrl+X
        if (e.ctrlKey && [65, 67, 86, 88].includes(e.keyCode)) {
            return;
        }

        // Разрешаем пробел
        if (e.keyCode === 32) {
            return;
        }

        // Запрещаем все, кроме цифр
        if (!/[0-9]/.test(e.key)) {
            e.preventDefault();
            showWarningMessage('Можно вводить только цифры!');
            return;
        }
    });
}

// ==================== ПРИМЕНЕНИЕ МАСКИ К ЯЧЕЙКЕ НДС ====================
function applyVatMaskToCell(cell) {
    const originalValue = cell.textContent.trim();

    // Форматируем начальное значение если нужно
    if (originalValue && !originalValue.includes('НДС')) {
        const numbersOnly = originalValue.replace(/\D/g, '');
        cell.textContent = numbersOnly ? `НДС ${numbersOnly}%` : 'НДС %';
    } else if (!originalValue) {
        cell.textContent = 'НДС %';
    }

    // Обработчик ввода для НДС
    cell.addEventListener('input', function (e) {
        handleVatMaskedInput(this);
    });



    // Обработчик потери фокуса - добавляем % если нет
    cell.addEventListener('blur', function (e) {
        const text = this.textContent;
        if (!text.includes('%')) {
            const numbersOnly = text.replace(/\D/g, '');
            this.textContent = numbersOnly ? `НДС ${numbersOnly}%` : 'НДС 0%';
        }
    });

    // Запрет ввода нечисловых символов для НДС
    cell.addEventListener('keydown', function (e) {
        // Разрешаем: backspace, delete, tab, стрелки, Enter
        if ([8, 9, 13, 37, 38, 39, 40, 46].includes(e.keyCode)) {
            return;
        }

        // Разрешаем Ctrl+A, Ctrl+C, Ctrl+V, Ctrl+X
        if (e.ctrlKey && [65, 67, 86, 88].includes(e.keyCode)) {
            return;
        }

        // Запрещаем все, кроме цифр
        if (!/[0-9]/.test(e.key)) {
            e.preventDefault();
            showWarningMessage('Можно вводить только цифры для НДС!');
            return;
        }
    });
}


// Обработчик ввода для масок числовых ячеек
// ==================== ФУНКЦИЯ 6: ОСНОВНОЙ ОБРАБОТЧИК ВВОДА (ЗАМЕНИТЬ) ====================
function handleMaskedInput(cell) {
    // Шаг 1: Сохраняем позицию курсора
    const cursorPosition = saveCursorPosition(cell);

    // Шаг 2: Получаем текущий текст
    const currentText = cell.textContent;
    const textBeforeCursor = currentText.substring(0, cursorPosition);

    // Шаг 3: Очищаем от нецифровых символов
    const numbersOnly = extractNumbersOnly(currentText);

    // Шаг 4: Форматируем с пробелами
    const formattedText = formatNumberWithSpaces(numbersOnly);

    // Шаг 5: Обновляем ячейку если текст изменился
    if (formattedText !== currentText) {
        cell.textContent = formattedText;

        // Шаг 6: Восстанавливаем курсор
        setTimeout(() => {
            const newPosition = calculateNewCursorPosition(
                cursorPosition,
                textBeforeCursor,
                formattedText
            );
            restoreCursorPosition(cell, newPosition);
        }, 0);
    }

    // Шаг 7: Запускаем пересчет
    scheduleRecalculation(cell);
}




// Обработчик ввода для маски НДС
// ==================== ОБРАБОТЧИК ВВОДА ДЛЯ НДС ====================
function handleVatMaskedInput(cell) {
    // Шаг 1: Сохраняем позицию курсора
    const cursorPosition = saveCursorPosition(cell);

    // Шаг 2: Получаем текущий текст и текст до курсора
    const currentText = cell.textContent;
    const textBeforeCursor = currentText.substring(0, cursorPosition);

    // Шаг 3: Очищаем от нецифровых символов
    const numbersOnly = currentText.replace(/\D/g, '');

    // Шаг 4: Форматируем как "НДС X%"
    const newText = numbersOnly ? `НДС ${numbersOnly}%` : 'НДС %';

    // Шаг 5: Обновляем ячейку если текст изменился
    if (newText !== currentText) {
        cell.textContent = newText;

        // Шаг 6: Восстанавливаем курсор с учетом нового формата
        setTimeout(() => {
            // Вычисляем новую позицию курсора
            let newPosition;

            if (cursorPosition <= 4) { // Курсор был в "НДС "
                newPosition = cursorPosition;
            } else {
                // Считаем сколько цифр было до курсора
                const digitsInTextBeforeCursor = textBeforeCursor.substring(4).replace(/\D/g, '').length;

                // Новая позиция: "НДС " + позиция после N-й цифры
                newPosition = 4 + Math.min(digitsInTextBeforeCursor, numbersOnly.length);
            }

            // Ограничиваем максимальную позицию
            newPosition = Math.min(newPosition, newText.length);

            restoreCursorPosition(cell, newPosition);
        }, 0);
    }

    // Шаг 7: Запускаем пересчет
    clearTimeout(tableChangeTimeout);
    tableChangeTimeout = setTimeout(() => {
        recalculateTotals();
    }, 300);
}

// ==================== ОСНОВНЫЕ ФУНКЦИИ КАЛЬКУЛЯТОРА ====================


// ==================== ПАРСИНГ ЧИСЛА ДЛЯ НДС ====================
function parseNumber(text) {
    if (!text || text.trim() === '') return 0;

    // Если текст содержит "НДС", извлекаем только число
    if (text.toString().includes('НДС')) {
        const numberMatch = text.toString().match(/(\d+)/);
        return numberMatch ? parseFloat(numberMatch[1]) : 0;
    }

    // Для форматированных чисел убираем пробелы
    return parseFloat(text.toString().replace(/\s/g, '')) || 0;
}

// Функция для форматирования чисел (используется для итогов)
function formatNumber(num) {
    return new Intl.NumberFormat('ru-RU').format(Math.round(num));
}

// Функция для показа сообщения
function showWarningMessage(message) {
    const existingMessage = document.getElementById('warning-message');
    if (existingMessage) {
        existingMessage.remove();
    }

    const warningDiv = document.createElement('div');
    warningDiv.id = 'warning-message';
    warningDiv.className = 'warning-message';
    warningDiv.textContent = message;

    document.body.appendChild(warningDiv);

    setTimeout(() => {
        if (warningDiv.parentNode) {
            warningDiv.remove();
        }
    }, 3000);
}

// Функция для запрета редактирования итоговых ячеек
function makeTotalsNonEditable() {
    const itogRows = document.querySelectorAll('tr.tr_itog');

    itogRows.forEach((row) => {
        const cells = row.cells;

        for (let i = 0; i < cells.length; i++) {
            const cell = cells[i];

            // Пропускаем ячейку с НДС в желтой строке
            if (row.classList.contains('yellow') && cell.classList.contains('name')) {
                continue;
            }

            // Для всех остальных ячеек - запрещаем редактирование
            if (cell.hasAttribute('contenteditable')) {
                cell.removeAttribute('contenteditable');
            }
            cell.style.cursor = 'not-allowed';
            // cell.style.backgroundColor = '#f5f5f5';

            // Добавляем обработчики
            cell.addEventListener('mousedown', function (e) {
                e.preventDefault();
                showWarningMessage('Итоговые ячейки нельзя редактировать!');
                return false;
            });

            cell.addEventListener('click', function (e) {
                e.preventDefault();
                showWarningMessage('Итоговые ячейки нельзя редактировать!');
                return false;
            });
        }
    });
}

// Основная функция пересчета строки
function recalculateRow(row, changedCellIndex) {
    try {
        const cells = row.cells;
        if (cells.length < 6) return;

        const quantityCell = cells[3]; // Кол-во
        const priceCell = cells[4];    // Цена
        const sumCell = cells[5];      // Сумма

        const quantity = parseNumber(quantityCell.textContent);
        const price = parseNumber(priceCell.textContent);
        const sum = parseNumber(sumCell.textContent);

        if (changedCellIndex === 3) { // Изменили количество
            const newSum = quantity * price;
            sumCell.textContent = formatNumberWithSpaces(newSum.toString());
        }
        else if (changedCellIndex === 4) { // Изменили цену
            const newSum = quantity * price;
            sumCell.textContent = formatNumberWithSpaces(newSum.toString());
        }
        else if (changedCellIndex === 5) { // Изменили сумму
            if (quantity > 0) {
                const newPrice = sum / quantity;
                priceCell.textContent = formatNumberWithSpaces(Math.round(newPrice).toString());
            } else {
                priceCell.textContent = '0';
            }
        }

    } catch (error) {
        console.error('❌ Ошибка при пересчете строки:', error);
    }
}



// Обработчик события input
function handleInputEvent(e) {
    const target = e.target;

    if (target.classList.contains('table_info') &&
        target.hasAttribute('contenteditable')) {

        const cellIndex = Array.from(target.parentNode.cells).indexOf(target);
        if (cellIndex === 3 || cellIndex === 4 || cellIndex === 5) {
            handleNumericInput(e);
        }
    }
    else if (target.classList.contains('table_itog') &&
        target.classList.contains('name') &&
        target.closest('tr.tr_itog.yellow')) {

        handleVATInput(e);
    }
}

// Обработчик ввода для числовых ячеек
function handleNumericInput(e) {
    const target = e.target;

    clearTimeout(tableChangeTimeout);
    tableChangeTimeout = setTimeout(() => {
        const row = target.closest('tr');
        if (row && !row.classList.contains('tr_itog') && !row.classList.contains('tr_name')) {
            const cellIndex = Array.from(target.parentNode.cells).indexOf(target);
            recalculateRow(row, cellIndex);
            recalculateTotals();
        }
    }, 100);
}

// Обработчик для НДС
function handleVATInput(e) {
    const target = e.target;

    const row = target.closest('tr');
    if (row &&
        row.classList.contains('tr_itog') &&
        row.classList.contains('yellow') &&
        target.classList.contains('name')) {

        clearTimeout(tableChangeTimeout);
        tableChangeTimeout = setTimeout(() => {
            recalculateTotals();
        }, 50);
    }
}

// Обработчик blur
function handleBlurEvent(e) {
    const target = e.target;

    if (target.classList.contains('table_info') &&
        target.hasAttribute('contenteditable')) {

        const cellIndex = Array.from(target.parentNode.cells).indexOf(target);
        if (cellIndex === 3 || cellIndex === 4 || cellIndex === 5) {
            if (!target.textContent || target.textContent.trim() === '') {
                target.textContent = '0';
            }
        }
    }
}

// ==================== ИНИЦИАЛИЗАЦИЯ ====================

function initTableHandlers() {
    // Добавляем обработчики
    document.addEventListener('input', handleInputEvent);
    document.addEventListener('blur', handleBlurEvent);


    // Инициализируем маски для всех редактируемых таблиц
    initNumericMasks();
    makeTotalsNonEditable();
    recalculateTotals();
}



// Запускаем при загрузке
document.addEventListener('DOMContentLoaded', function () {
    setTimeout(() => {
        initTableHandlers();
        initGlobalTableEditing(); // Инициализируем автоматическое редактирование
    }, 100);
});

// Функции для работы с конкретными таблицами (вызываются из crm-tablemore.js)
function initTableHandlersForTable(table) {
    // Инициализируем маски для конкретной таблицы
    initNumericMasksForTable(table);
    makeTotalsNonEditableForTable(table);
    recalculateTotalsForTable(table);
}

function initNumericMasksForTable(table) {
    const numericCells = table.querySelectorAll('.table_info[contenteditable="true"]');

    numericCells.forEach(cell => {
        const cellIndex = Array.from(cell.parentNode.cells).indexOf(cell);

        if (cellIndex === 3 || cellIndex === 4 || cellIndex === 5) {
            applyMaskToCell(cell);
        }
    });

    // Маска для ячейки НДС в конкретной таблице
    const vatCells = table.querySelectorAll('tr.tr_itog.yellow td.table_itog.name[contenteditable="true"]');
    vatCells.forEach(cell => {
        applyVatMaskToCell(cell);
    });
}

function makeTotalsNonEditableForTable(table) {
    const itogRows = table.querySelectorAll('tr.tr_itog');

    itogRows.forEach((row) => {
        const cells = row.cells;

        for (let i = 0; i < cells.length; i++) {
            const cell = cells[i];

            // Пропускаем ячейку с НДС в желтой строке
            if (row.classList.contains('yellow') && cell.classList.contains('name')) {
                continue;
            }

            // Для всех остальных ячеек - запрещаем редактирование
            if (cell.hasAttribute('contenteditable')) {
                cell.removeAttribute('contenteditable');
            }
            cell.style.cursor = 'not-allowed';


            // Добавляем обработчики с красным индикатором
            cell.addEventListener('mousedown', function (e) {
                e.preventDefault();
                showRedWarningMessage('Итоговые ячейки нельзя редактировать!', cell);
                return false;
            });

            cell.addEventListener('click', function (e) {
                e.preventDefault();
                showRedWarningMessage('Итоговые ячейки нельзя редактировать!', cell);
                return false;
            });

            // ЛОГИРОВАНИЕ ДЛЯ ПРОВЕРКИ - будет выполнено для каждой ячейки
            console.log('Обработчик уведомления добавлен для ячейки:', cell);
        }
    });
}

// Функция для показа красного предупреждения с классом none
function showRedWarningMessage(message, element) {
    const existingMessage = document.getElementById('red-warning-message');
    if (existingMessage) {
        existingMessage.remove();
    }

    const warningDiv = document.createElement('div');
    warningDiv.id = 'red-warning-message';
    warningDiv.className = 'warning-message none';
    warningDiv.textContent = message;
    warningDiv.style.cssText = `
        position: absolute;
        background: #dc3545;
        color: white;
        padding: 8px 12px;
        border-radius: 4px;
        font-size: 12px;
        font-weight: bold;
        z-index: 100001;
        opacity: 0.9;
    `;

    // Позиционируем рядом с элементом
    const rect = element.getBoundingClientRect();
    warningDiv.style.top = (rect.top + window.scrollY) + 'px';
    warningDiv.style.left = (rect.right + window.scrollX + 10) + 'px';

    document.body.appendChild(warningDiv);

    setTimeout(() => {
        if (warningDiv.parentNode) {
            warningDiv.remove();
        }
    }, 2000);
}

function recalculateTotalsForTable(table) {
    try {
        const infoRows = table.querySelectorAll('tr.tr_info:not(.tr_itog)');
        let totalSum = 0;

        infoRows.forEach(row => {
            const sumCell = row.cells[5];
            if (sumCell) {
                const sum = parseNumber(sumCell.textContent);
                totalSum += sum;
            }
        });

        const vatRow = table.querySelector('tr.tr_itog.yellow');
        let vatPercent = 22; // значение по умолчанию

        if (vatRow) {
            const vatCell = vatRow.querySelector('td.table_itog.name');
            if (vatCell) {
                vatPercent = parseNumber(vatCell.textContent);
            }
        }

        const vatAmount = Math.round(totalSum * vatPercent / 100);
        const totalWithVAT = totalSum + vatAmount;

        // Обновляем итоговые ячейки по классам
        const totalSumElement = table.querySelector('.total-sum');
        const vatAmountElement = table.querySelector('.vat-amount');
        const totalWithVATElement = table.querySelector('.total-with-vat');

        if (totalSumElement) {
            totalSumElement.textContent = formatNumber(totalSum);
        }

        if (vatAmountElement) {
            vatAmountElement.textContent = formatNumber(vatAmount);
        }



        if (totalWithVATElement) {

            const mainValueSpan = totalWithVATElement.querySelector('div:first-child span');
            if (mainValueSpan) {
                mainValueSpan.textContent = formatNumberWithSpaces(totalWithVAT.toString());
            }

        }

        console.log('📊 Пересчитано для таблицы:', { totalSum, vatPercent, vatAmount, totalWithVAT });

    } catch (error) {
        console.error('❌ Ошибка при пересчете итогов таблицы:', error);
    }
}


function recalculateTotals(table = null) {
    if (table) {
        recalculateTotalsForTable(table);
    } else {
        // Старая логика для обратной совместимости
        const tables = document.querySelectorAll('.textcols_more');
        tables.forEach(table => {
            if (table.isEditing) {
                recalculateTotalsForTable(table);
            }
        });
    }
}