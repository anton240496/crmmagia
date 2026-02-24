
document.addEventListener('click', function (e) {
    const saveFuncContainer = e.target.closest('.save_file_editor_func');
    if (!saveFuncContainer) return;

    // Обработка кнопки "Сохранить"
    if (e.target.classList.contains('save_file_editor_open')) {
        const interface = saveFuncContainer.querySelector('.save_file_editor_interface');
        const $fileWindow = $(saveFuncContainer).closest('.file-creation-window');
        const $redactorInfo = $fileWindow.find('.redactor_file');
        const $fileNameInput = $fileWindow.find('.file-name-input');

        // 🔥 ПРОВЕРЯЕМ ЧТО СЕЙЧАС РЕДАКТИРУЕТСЯ
        var currentFile = $redactorInfo.text().trim();

        if (currentFile !== 'редактируется новый') {
            // 🔥 ЕСЛИ РЕДАКТИРУЕТСЯ СУЩЕСТВУЮЩИЙ ФАЙЛ - ЗАПОЛНЯЕМ INPUT
            var fileName = currentFile.replace('редактируется ', '');
            $fileNameInput.val(fileName.replace(/\.html?$/i, '')); // Убираем расширение .html если есть
        } else {
            // 🔥 ЕСЛИ РЕДАКТИРУЕТСЯ НОВЫЙ - ОЧИЩАЕМ INPUT
            $fileNameInput.val('');
        }

        interface.style.display = 'block';
        e.target.style.display = 'none';
    }

    // 🔥 ОБРАБОТКА КНОПКИ "ЗАМЕНИТЬ" - ДОБАВЛЯЕМ ЗДЕСЬ
    if (e.target.classList.contains('editor_replace_btn')) {
        e.preventDefault();
        e.stopPropagation();



        const $fileWindow = $(e.target).closest('.file-creation-window');
        const $redactorInfo = $fileWindow.find('.redactor_file');
        const currentFile = $redactorInfo.text().trim();

        // 🔥 ПРОВЕРЯЕМ ЧТО РЕДАКТИРУЕТСЯ КОНКРЕТНЫЙ ФАЙЛ
        if (currentFile === 'редактируется новый') {
            showReplaceMessage(saveFuncContainer);
            return;
        }

        // 🔥 ЗАПУСКАЕМ ПРОЦЕСС ЗАМЕНЫ
        replaceFile($fileWindow);
    }

    // Обработка кнопок "новый", "отмена"
    if (e.target.classList.contains('editor_cancel_btn') || e.target.classList.contains('editor_new_btn')) {

        if (e.target.classList.contains('editor_cancel_btn')) {
            const interface = saveFuncContainer.querySelector('.save_file_editor_interface');
            const openBtn = saveFuncContainer.querySelector('.save_file_editor_open');

            interface.style.display = 'none';
            openBtn.style.display = 'block';

            // 🔥 ОЧИЩАЕМ ОШИБКИ ПРИ ОТМЕНЕ
            const $fileWindow = $(saveFuncContainer).closest('.file-creation-window');
            $fileWindow.find('.file-name-error').hide();
        } else if (e.target.classList.contains('editor_new_btn')) {
            console.log('Новый файл');
            // логика для нового файла
        }
    }
});


// 🔥 ФУНКЦИЯ ЗАМЕНЫ ФАЙЛА
function replaceFile($fileWindow) {
    var $redactorInfo = $fileWindow.find('.redactor_file');
    var $editor = $fileWindow.find('.file-content-editor');
    var $fileNameInput = $fileWindow.find('.file-name-input');
    var $status = $fileWindow.find('.file-status');
    var $replaceBtn = $fileWindow.find('.editor_replace_btn');

    // Получаем текущее имя файла из надписи
    var currentFileText = $redactorInfo.text().trim();
    var currentFileName = currentFileText.replace('редактируется ', '');
    var newFileName = $fileNameInput.val().trim();

    // 🔥 ИСПРАВЛЕНИЕ: ЕСЛИ INPUT ПУСТОЙ, ИСПОЛЬЗУЕМ ТЕКУЩЕЕ ИМЯ ФАЙЛА
    if (!newFileName) {
        newFileName = currentFileName;
        console.log('⚠️ DEBUG: input пустой, используем текущее имя файла:', newFileName);
    }

    console.log('🔍 DEBUG replaceFile:');
    console.log('- currentFileText:', currentFileText);
    console.log('- currentFileName:', currentFileName);
    console.log('- newFileName:', newFileName);

    //  ИСПРАВЛЕНИЕ: ИЩЕМ ФАЙЛ ПО АКТИВНОМУ СОСТОЯНИЮ, А НЕ ПО ИМЕНИ
    var $currentFileItem = $fileWindow.find('.save_file_item.active'); // Ищем активный файл
    if (!$currentFileItem.length) {
        // Если нет активного, ищем по текущему имени в обоих местах
        $currentFileItem = null;
        $fileWindow.find('.save_file_item').each(function () {
            var $item = $(this);
            // Проверяем оба возможных места хранения имени
            var fileName1 = $item.find('.file-name-text').text().trim();
            var fileName2 = $item.find('.file-item-name').text().trim();
            console.log('- Сравниваем:', fileName1, 'и', fileName2, 'с', currentFileName);
            if (fileName1 === currentFileName || fileName2 === currentFileName) {
                $currentFileItem = $item;
                return false; // break the loop
            }
        });
    }

    console.log('- Найденный $currentFileItem:', $currentFileItem ? $currentFileItem.length : 0);

    var fileId = $currentFileItem ? $currentFileItem.find('.file-delete').data('file-id') : null;
    console.log('- Найденный fileId:', fileId);

    if (!fileId) {
        $status.html('<div class="notice notice-error">Не удалось найти ID файла для замены</div>');
        console.log('❌ DEBUG: fileId не найден!');
        console.log('❌ Проверьте структуру HTML элемента файла:');
        console.log($currentFileItem ? $currentFileItem.html() : 'Элемент не найден');
        return;
    }

    var fileContent = $editor.html();
    if (!fileContent || fileContent.trim() === '' || fileContent === '<br>') {
        $status.html('<div class="notice notice-error">Введите текст для документа HTML</div>');
        return;
    }

    console.log('🔧 DEBUG: Замена файла:', { fileId, currentFileName, newFileName });

    // Сохраняем ссылки на элементы перед AJAX
    var $fileDateElement = $currentFileItem.find('.file-date');

    // Анимация изменения цвета
    $fileDateElement.css({
        'color': '#31a060',
    });

    setTimeout(() => {
        $fileDateElement.css({
            'color': '',
        });
    }, 5000);

    // Блокируем кнопку
    var originalText = $replaceBtn.text();
    $replaceBtn.text('Заменить').prop('disabled', true);
    $status.html('<div class="notice notice-info">Замена файла...</div>');

    $.ajax({
        url: crm_ajax.ajaxurl,
        type: 'POST',
        data: {
            action: 'replace_file',
            file_id: fileId,
            file_content: fileContent,
            custom_file_name: newFileName
        },
        success: function (response) {
            console.log('✅ DEBUG: Файл заменен:', response);

            if (response.success) {
                $status.html('<div class="notice notice-success">' + response.data.message + '</div>');

                // 🔥 ОБНОВЛЯЕМ ВСЕ ИМЕНА ФАЙЛА В ИНТЕРФЕЙСЕ
                $redactorInfo.text('редактируется ' + response.data.file_name);
                $fileNameInput.val(response.data.file_name); // 🔥 ОБНОВЛЯЕМ ПОЛЕ ВВОДА

                // 🔥 ОБНОВЛЯЕМ ИМЯ ВО ВСЕХ МЕСТАХ
                $currentFileItem.find('.file-name-text').text(response.data.file_name);
                $currentFileItem.find('.file-item-name').text(response.data.file_name);

                // 🔥 ПОКАЗЫВАЕМ СООБЩЕНИЕ НА КНОПКЕ
                showReplaceSuccessMessage($replaceBtn, 'Файл заменен');

                // 🔥 ОБНОВЛЯЕМ ВРЕМЯ
                var now = new Date();
                var newTime =
                    ('0' + now.getDate()).slice(-2) + '.' +
                    ('0' + (now.getMonth() + 1)).slice(-2) + '.' +
                    now.getFullYear() + ' ' +
                    ('0' + now.getHours()).slice(-2) + ':' +
                    ('0' + now.getMinutes()).slice(-2);

                // Находим элемент времени снова (на случай если DOM изменился)
                var $updatedFileDate = $currentFileItem.find('.file-date');
                $updatedFileDate.text(newTime);

                // 🔥 ПОДСВЕЧИВАЕМ ОРАНЖЕВЫМ НА 5 СЕКУНД
                $updatedFileDate.css({
                    'color': '#31a060',
                });

                setTimeout(() => {
                    $updatedFileDate.css({
                        'color': '',
                    });
                }, 5000);

            } else {
                $status.html('<div class="notice notice-error">Ошибка: ' + response.data + '</div>');
            }

            $replaceBtn.text(originalText).prop('disabled', false);
        },
        error: function (xhr, status, error) {
            console.log('❌ DEBUG: Ошибка замены файла:', error);
            $status.html('<div class="notice notice-error">Ошибка замены файла: ' + error + '</div>');
            $replaceBtn.text(originalText).prop('disabled', false);

            // Возвращаем цвет при ошибке
            $fileDateElement.css({
                'color': '',
            });
        }
    });
}

// 🔥 ВАРИАНТ С ИЗМЕНЕНИЕМ ТЕКСТА КНОПКИ - РАСКОММЕНТИРОВАТЬ ТАЙМАУТ
function showReplaceSuccessMessage($replaceBtn, message) {
    var originalText = $replaceBtn.text();

    // Меняем текст кнопки
    $replaceBtn.text('✓ ' + message);
    $replaceBtn.css('background-color', '#d4edda');
    $replaceBtn.css('color', '#155724');

    // 🔥 РАСКОММЕНТИРОВАТЬ ТАЙМАУТ - ВОЗВРАЩАЕМ ОБРАТНО ЧЕРЕЗ 5 СЕКУНД
    setTimeout(() => {
        $replaceBtn.text(originalText);
        $replaceBtn.css('background-color', '');
        $replaceBtn.css('color', '');
    }, 5000);
}

// Функция для активации кнопки "заменить"
function activateReplaceButton(container) {
    const replaceBtn = container.querySelector('.editor_replace_btn');
    if (replaceBtn) {
        replaceBtn.classList.remove('disabled');
        replaceBtn.style.opacity = '1';
        replaceBtn.style.cursor = 'pointer';
        replaceBtn.title = 'Заменить существующий файл';
    }
}

function findDialogId($element) {
    // Способ 1: Ищем в ближайших элементах с data-атрибутами
    var dialogId = $element.closest('[data-dialog-id]').data('dialog-id');
    if (dialogId) return dialogId;

    // Способ 2: Ищем в родительских контейнерах диалога
    dialogId = $element.closest('.dialog-container').data('id') ||
        $element.closest('.dialog').data('id') ||
        $element.closest('[id*="dialog"]').data('id');

    // Способ 3: Ищем по классам или ID
    var $dialogElement = $element.closest('.crm-dialog, .dialog-item, [id*="dialog"]');
    if ($dialogElement.length) {
        // Пробуем извлечь ID из атрибута id
        var idAttr = $dialogElement.attr('id');
        if (idAttr) {
            var match = idAttr.match(/dialog[_-]?(\d+)/i);
            if (match) return match[1];
        }

        // Пробуем из data-атрибутов
        dialogId = $dialogElement.data('dialog-id') ||
            $dialogElement.data('dialog') ||
            $dialogElement.data('id');
    }

    console.log('🔍 DEBUG: Найден dialog_id:', dialogId);
    return dialogId;
}

function findLeadId($element) {
    // Аналогично ищем lead_id
    var leadId = $element.closest('[data-lead-id]').data('lead-id');
    if (leadId) return leadId;

    leadId = $element.closest('.lead-container').data('id') ||
        $element.closest('.lead').data('id') ||
        $element.closest('[id*="lead"]').data('id');

    console.log('🔍 DEBUG: Найден lead_id:', leadId);
    return leadId;
}


// Функция для загрузки списка файлов при инициализации
function initFilesLists() {
    console.log('🟢 DEBUG: Инициализация списков файлов');

    $('.file-creation-window').each(function () {
        var $fileWindow = $(this);

        // 🔥 ДИНАМИЧЕСКИ ИЩЕМ ID
        var dialogId = findDialogId($fileWindow);
        var leadId = findLeadId($fileWindow);

        if (dialogId) {
            console.log('🔍 DEBUG: Загружаем файлы для диалога:', dialogId);
            // Сохраняем найденные ID в data-атрибуты для последующего использования
            $fileWindow.attr('data-dialog-id', dialogId);
            if (leadId) {
                $fileWindow.attr('data-lead-id', leadId);
            }
            loadFilesList(dialogId);
        } else {
            console.log('❌ DEBUG: Не удалось найти dialog_id для окна');
            // Показываем сообщение "файлов нет"
            var $filesList = $fileWindow.find('.save_file_spisok');
            if ($filesList.length) {
                $filesList.empty();
                $filesList.append('<li class="save_file_empty">файлов в диалоге нет</li>');
            }
        }
    });
}

// Функция загрузки списка файлов
function loadFilesList(dialogId) {
    console.log('📥 DEBUG: Запрос файлов для диалога:', dialogId);

    $.ajax({
        url: crm_ajax.ajaxurl,
        type: 'POST',
        data: {
            action: 'get_files_list',
            dialog_id: dialogId
        },
        success: function (response) {
            console.log('✅ DEBUG: Получены файлы для диалога', dialogId, ':', response);

            if (response.success) {
                updateFilesList(response.data.files, dialogId);
            } else {
                console.log('❌ Ошибка загрузки списка файлов:', response.data);
                showEmptyFilesList(dialogId);
            }
        },
        error: function (xhr, status, error) {
            console.log('❌ Ошибка AJAX при загрузке файлов:', error);
            showEmptyFilesList(dialogId);
        }
    });
}


// Функция обновления списка файлов
function updateFilesList(files, dialogId) {
    console.log('🔄 DEBUG: Обновление списка для диалога', dialogId, 'файлов:', files ? files.length : 0);

    // Находим блок для этого диалога
    var $fileWindow = $('.file-creation-window[data-dialog-id="' + dialogId + '"]');
    if ($fileWindow.length === 0) {
        $fileWindow = $('[data-dialog-id="' + dialogId + '"]').closest('.file-creation-window');
    }

    var $filesList = $fileWindow.find('.save_file_spisok');

    if (!$filesList.length) {
        console.log('❌ DEBUG: Не найден блок .save_file_spisok для диалога', dialogId);
        return;
    }


    //  всегда показываем либо файлы, либо сообщение "файлов нет"

    if (files && files.length > 0) {
        console.log('✅ DEBUG: Показываем', files.length, 'файлов для диалога', dialogId);

        // Очищаем список только если есть файлы для показа
        $filesList.empty();

        // Есть файлы - показываем список
        files.forEach(function (file) {
            var fileType = file.html ? 'HTML' : 'File';
            var fileDate = new Date(file.created_at).toLocaleString('ru-RU', {
                day: '2-digit',
                month: '2-digit',
                year: 'numeric',
                hour: '2-digit',
                minute: '2-digit',
                hour12: false
            });

            var $fileItem = $('<li class="save_file_item"></li>');
            $fileItem.html(`
<div class="file-item-name">
    <span class="file-name-text">${file.file_name}</span>
    <div class="file-name-edit" style="display: none; align-items: center; gap: 5px;">
        <div><input type="text" class="file-name-edit-input" value="${file.file_name.replace(/\.html?$/i, '')}" placeholder="Введите имя файла" style="flex: 1; padding: 2px 5px; font-size: 13px;"></div>
        <button type="button" class="file-name-save-btn">&#10004;</button>
        <button type="button" class="file-name-cancel-btn">&#10006;</button>
    </div>
</div>
<div class="file-item-info">
    <span class="file-date">${fileDate}</span>
</div>
<div class="file-actions">
    <a href="${file.file_url}" class="file-download" data-file-url="${file.file_url}" data-file-name="${file.file_name}" title="Открыть в редакторе">📥</a>
    <button type="button" class="file_edit_editor" data-file-id="${file.id}" data-file-name="${file.file_name}" title="Изменить файл">✏️</button>
    <button type="button" class="file-delete" data-file-id="${file.id}" data-file-name="${file.file_name}" title="удалить файл">🗑️</button>
</div>
            `);
            $filesList.append($fileItem);
        });
    } else {
        console.log('ℹ️ DEBUG: Нет файлов для диалога', dialogId);
        // 🔥 ВАЖНОЕ ИСПРАВЛЕНИЕ: Очищаем и показываем сообщение "файлов нет"
        $filesList.empty();
        $filesList.append('<li class="save_file_empty">файлов в диалоге нет</li>');
    }
}

// 🔥 ДОБАВЛЯЕМ ОБРАБОТЧИК ДЛЯ КНОПКИ "НОВЫЙ" - УБЕДИТЕСЬ ЧТО ОН ЕСТЬ
$(document).on('click', '.editor_new_btn', function (e) {
    e.preventDefault();
    e.stopPropagation();

    var $button = $(this);
    var $container = $button.closest('.save_file_editor_func');
    var $fileWindow = $button.closest('.file-creation-window');

    // Находим lead_id и dialog_id
    var leadId = $fileWindow.data('lead-id') || $fileWindow.find('[data-lead-id]').data('lead-id');
    var dialogId = $fileWindow.data('dialog-id') || $fileWindow.find('[data-dialog-id]').data('dialog-id');

    // Получаем имя файла из input
    var $fileNameInput = $container.find('.file-name-input');
    var customFileName = $fileNameInput.val().trim();
    var $error = $container.find('.file-name-error');

    console.log('🔍 DEBUG: Создание HTML для:', { leadId, dialogId, customFileName });

    if (!customFileName) {
        $error.text('Введите имя файла').show();
        return;
    }

    // Находим контент редактора
    var fileContent = $fileWindow.find('.file-content-editor').html();
    var $status = $fileWindow.find('.file-status');

    if (!fileContent || fileContent.trim() === '' || fileContent === '<br>') {
        $status.html('<div class="notice notice-error">Введите текст для документа HTML</div>');
        return;
    }

    // 🔥 ПРОВЕРЯЕМ СУЩЕСТВОВАНИЕ ФАЙЛА ПЕРЕД ОТПРАВКОЙ
    if ($error.is(':visible')) {
        $status.html('<div class="notice notice-error">Файл с таким именем уже существует. Используйте другое имя.</div>');
        return;
    }

    // 🔥 ОБНОВЛЯЕМ ИНФОРМАЦИЮ О РЕДАКТИРУЕМОМ ФАЙЛЕ
    var $redactorInfo = $fileWindow.find('.redactor_file');
    $redactorInfo.text('редактируется новый');

    var originalText = $button.text();
    $button.text('Создание HTML...').prop('disabled', true);
    $status.html('<div class="notice notice-info">Создание HTML документа...</div>');

    $.ajax({
        url: crm_ajax.ajaxurl,
        type: 'POST',
        data: {
            action: 'generate_html_file',
            lead_id: leadId,
            dialog_id: dialogId,
            file_content: fileContent,
            custom_file_name: customFileName
        },
        success: function (response) {
            console.log('✅ DEBUG: Успешный ответ HTML:', response);

            if (response.success) {
                $status.html('<div class="notice notice-success">' + response.data.message + '</div>');
                console.log('✅ Файл создан:', response.data.file_name);

                // 🔥 ВАЖНО: ВОССТАНАВЛИВАЕМ КНОПКУ "НОВЫЙ"
                $button.text('новый').prop('disabled', false);

                // Очищаем input и скрываем ошибку
                $fileNameInput.val('');
                $error.hide();

                // Скрываем интерфейс
                $container.find('.save_file_editor_interface').hide();
                $container.find('.save_file_editor_open').show();

                // 🔥 ВАЖНОЕ ИСПРАВЛЕНИЕ: ОБНОВЛЯЕМ СПИСОК ФАЙЛОВ ПОСЛЕ СОЗДАНИЯ НОВОГО
                // loadFilesList(dialogId);
                loadFilesList(dialogId);

                // 🔥 ОБНОВЛЯЕМ ВРЕМЯ ПОСЛЕ ЗАГРУЗКИ
                setTimeout(function () {
                    var $newFileItem = $fileWindow.find('.save_file_item').first();
                    if ($newFileItem.length) {
                        var now = new Date();
                        var newTime =
                            ('0' + now.getDate()).slice(-2) + '.' +
                            ('0' + (now.getMonth() + 1)).slice(-2) + '.' +
                            now.getFullYear() + ' ' +
                            ('0' + now.getHours()).slice(-2) + ':' +
                            ('0' + now.getMinutes()).slice(-2);

                        $newFileItem.find('.file-date').text(newTime);
                    }
                }, 1000);
                // Обновляем информацию о редактируемом файле
                $redactorInfo.text('редактируется ' + response.data.file_name);

                // 🔥 АКТИВИРУЕМ КНОПКУ "ЗАМЕНИТЬ" ТОЛЬКО ПРИ УСПЕХЕ
                $fileWindow.find('.editor_replace_btn').removeClass('disabled').css({ 'opacity': '1', 'cursor': 'pointer' });

            } else {
                // Если ошибка из-за существующего файла
                if (response.data.includes('уже существует')) {
                    $error.text(response.data).show();
                    $status.html('<div class="notice notice-error">Файл с таким именем уже существует. Используйте другое имя.</div>');
                } else {
                    $status.html('<div class="notice notice-error">Ошибка: ' + response.data + '</div>');
                }
                // 🔥 ВОССТАНАВЛИВАЕМ КНОПКУ ПРИ ОШИБКЕ
                $button.text('новый').prop('disabled', false);
            }
        },
        error: function (xhr, status, error) {
            console.log('❌ DEBUG: Ошибка AJAX HTML:', error);
            $status.html('<div class="notice notice-error">Ошибка создания HTML: ' + error + '</div>');
            // 🔥 ВОССТАНАВЛИВАЕМ КНОПКУ ПРИ ОШИБКЕ AJAX
            $button.text('новый').prop('disabled', false);
        }
    });
});

// 🔥 ДОБАВЛЯЕМ ФУНКЦИЮ ДЛЯ ПРОВЕРКИ ВИДИМОСТИ СПИСКА ФАЙЛОВ
function checkFilesListVisibility(dialogId) {
    var $fileWindow = $('.file-creation-window[data-dialog-id="' + dialogId + '"]');
    var $filesList = $fileWindow.find('.save_file_spisok');

    if ($filesList.length && $filesList.is(':empty')) {
        console.log('⚠️ DEBUG: Список файлов пуст, добавляем сообщение');
        $filesList.append('<li class="save_file_empty">файлов в диалоге нет</li>');
    }
}

// Функция показа пустого списка
function showEmptyFilesList(dialogId) {
    console.log('📭 DEBUG: Показываем пустой список для диалога', dialogId);

    var $fileWindow = $('.file-creation-window[data-dialog-id="' + dialogId + '"]');
    var $filesList = $fileWindow.find('.save_file_spisok');

    if ($filesList.length) {
        $filesList.empty();
        $filesList.append('<li class="save_file_empty">файлов в диалоге нет</li>');
    }
}

// Проверка имени файла на лету при вводе
var fileNameCheckTimeout;
$(document).on('input', '.file-name-input', function () {
    var $input = $(this);
    var $error = $input.siblings('.file-name-error');
    var fileName = $input.val().trim();

    // Скрываем ошибку при изменении текста
    $error.hide();

    // Очищаем предыдущий таймер
    clearTimeout(fileNameCheckTimeout);

    // Если имя не пустое, проверяем его с задержкой
    if (fileName) {
        fileNameCheckTimeout = setTimeout(function() {
            checkFileNameExists(fileName, $input);
        }, 500); // Ждем 800мс после остановки печати
    }
});
// Функция проверки существования файла
function checkFileNameExists(fileName, $input) {
    var $fileWindow = $input.closest('.file-creation-window');
    var leadId = $fileWindow.data('lead-id') || $fileWindow.find('[data-lead-id]').data('lead-id');
    var dialogId = $fileWindow.data('dialog-id') || $fileWindow.find('[data-dialog-id]').data('dialog-id');

    if (!leadId || !dialogId) return;

    $.ajax({
        url: crm_ajax.ajaxurl,
        type: 'POST',
        data: {
            action: 'check_file_name_exists',
            file_name: fileName,
            dialog_id: dialogId,
            lead_id: leadId
        },
        success: function (response) {
            if (response.success && response.data.exists) {
                var $error = $input.siblings('.file-name-error');
                $error.text('этот файл уже существует').show();
            }
        }
    });
}



// 🔥 ВАЖНО: Вызываем инициализацию при загрузке документа
$(document).ready(function () {
    console.log('🚀 DEBUG: Документ загружен, инициализируем списки файлов');
    initFilesLists();
});

// Обработчик кнопки "📥" - открытие файла в редакторе
// Глобальная переменная для блокировки
var isFileLoading = false;



function canActivateReplaceButton($fileWindow) {
    var $redactorInfo = $fileWindow.find('.redactor_file');
    var currentText = $redactorInfo.text().trim();

    // 🔥 ЕСЛИ РЕДАКТИРУЕТСЯ КОНКРЕТНЫЙ ФАЙЛ - МОЖНО АКТИВИРОВАТЬ
    // 🔥 ЕСЛИ "РЕДАКТИРУЕТСЯ НОВЫЙ" - НЕЛЬЗЯ АКТИВИРОВАТЬ
    return currentText !== 'редактируется новый' && currentText.startsWith('редактируется');
}
// Обработчик кнопки "📥" - открытие файла в редакторе
// В обработчике открытия файла
$(document).on('click', '.file-download', function (e) {
    e.preventDefault();

    if (isFileLoading) {
        console.log('⏳ Загрузка уже идет, подождите...');
        return;
    }

    isFileLoading = true;

    var $link = $(this);
    var fileUrl = $link.data('file-url') || $link.attr('href');
    var fileName = $link.closest('.save_file_item').find('.file-item-name').text().trim() || 'файл';
    var $fileWindow = $link.closest('.file-creation-window');
    var $editor = $fileWindow.find('.file-content-editor');
    var $redactorInfo = $fileWindow.find('.redactor_file');

    console.log('🔍 DEBUG: Открываем файл в редакторе:');
    console.log('- fileUrl:', fileUrl);
    console.log('- fileName:', fileName);

    // ОБНОВЛЯЕМ ИНФОРМАЦИЮ О РЕДАКТИРУЕМОМ ФАЙЛЕ
    $redactorInfo.text('редактируется ' + fileName);

    // 🔥 ЗАПОЛНЯЕМ INPUT ИМЕНЕМ ФАЙЛА
    var $fileNameInput = $fileWindow.find('.file-name-input');
    $fileNameInput.val(fileName.replace(/\.html?$/i, '')); // Убираем расширение .html если есть

    var $error = $fileWindow.find('.file-name-error');
    $error.text('Этот файл уже существует').show();
    $error.css('color', 'red');

    // 🔥 АКТИВИРУЕМ КНОПКУ "ЗАМЕНИТЬ" ТАК КАК РЕДАКТИРУЕМ СУЩЕСТВУЮЩИЙ ФАЙЛ
    $fileWindow.find('.editor_replace_btn').removeClass('disabled').css({ 'opacity': '1', 'cursor': 'pointer' });

    // Показываем загрузку
    var originalHtml = $link.html();
    $link.html('⏳').prop('disabled', true);

    // 🔥 БЛОКИРУЕМ ВСЕ КНОПКИ ЗАГРУЗКИ
    $('.file-download').prop('disabled', true).css('opacity', '0.5');

    console.log('⏰ Искусственная задержка 2 секунды...');

    setTimeout(function () {
        // Загружаем содержимое файла
        $.ajax({
            url: fileUrl,
            type: 'GET',
            dataType: 'html',
            success: function (htmlContent) {
                console.log('✅ DEBUG: Файл загружен, размер:', htmlContent.length);

                // 🔥 ЕЩЕ ЗАДЕРЖКА ДЛЯ ЭФФЕКТА ОБРАБОТКИ - 1 СЕКУНДА
                setTimeout(function () {
                    // Извлекаем содержимое из .wap
                    var $tempDiv = $('<div>').html(htmlContent);
                    var wapContent = $tempDiv.find('.wap').html();

                    console.log('🔍 DEBUG: Найден контент в .wap:', wapContent ? wapContent.length : 0);

                    if (wapContent) {
                        // Загружаем содержимое в редактор
                        $editor.html('<div class="wap">' + wapContent + '</div>');
                        console.log('✅ Содержимое загружено в редактор');
                    } else {
                        // Если нет .wap, ищем body content
                        var bodyContent = $tempDiv.find('body .content-wrapper').html() ||
                            $tempDiv.find('body').html() ||
                            htmlContent;
                        $editor.html('<div class="wap">' + bodyContent + '</div>');
                        console.log('✅ Загружено полное содержимое');
                    }

                    // 🔥 СНИМАЕМ БЛОКИРОВКУ
                    isFileLoading = false;
                    $('.file-download').prop('disabled', false).css('opacity', '1');
                    $link.html(originalHtml);

                    console.log('🎉 Загрузка завершена! Блокировка снята.');
                }, 0); // 1 секунда задержки
            },
            error: function (xhr, status, error) {
                console.log('❌ DEBUG: Ошибка загрузки файла:', error);

                // 🔥 СНИМАЕМ БЛОКИРОВКУ ПРИ ОШИБКЕ
                isFileLoading = false;
                $('.file-download').prop('disabled', false).css('opacity', '1');
                $link.html(originalHtml);

                // 🔥 ПРИ ОШИБКЕ ТОЖЕ НЕ ОТКРЫВАЕМ В НОВОЙ ВКЛАДКЕ
                console.log('❌ Файл не загружен, но в новой вкладке не открываем');
            }
        });
    }, 0); // 2 секунды начальной задержки
});

// Обработчик кнопки удаления файла
$(document).on('click', '.file-delete', function (e) {
    e.preventDefault();
    e.stopPropagation();


    var $button = $(this);
    var fileId = $button.data('file-id');
    var fileName = $button.data('file-name');
    var $fileItem = $button.closest('.save_file_item');
    var $fileWindow = $button.closest('.file-creation-window');
    var dialogId = $fileWindow.data('dialog-id');
    var $redactorInfo = $fileWindow.find('.redactor_file');

    console.log('🗑️ DEBUG: Удаление файла:', { fileId, fileName, dialogId });

    // 🔥 ПРОВЕРЯЕМ - ЕСЛИ УДАЛЯЕМ ТЕКУЩИЙ РЕДАКТИРУЕМЫЙ ФАЙЛ
    var currentFile = $redactorInfo.text().trim();
    if (currentFile === 'редактируется ' + fileName) {
        // 🔥 МЕНЯЕМ НА "редактируется новый"
        $redactorInfo.text('редактируется новый');

        // 🔥 ДЕАКТИВИРУЕМ КНОПКУ "ЗАМЕНИТЬ"
        $fileWindow.find('.editor_replace_btn').addClass('disabled').css({ 'opacity': '0.5', 'cursor': 'not-allowed' });
    }

    // Подтверждение удаления
    if (!confirm('Вы уверены, что хотите удалить файл "' + fileName + '"?\n\nФайл будет удален из базы данных и файловой системы.')) {
        return;
    }

    // Сохраняем оригинальный текст кнопки
    var originalHtml = $button.html();
    $button.html('⏳').prop('disabled', true);

    $.ajax({
        url: crm_ajax.ajaxurl,
        type: 'POST',
        data: {
            action: 'delete_file',
            file_id: fileId
        },
        success: function (response) {
            console.log('✅ DEBUG: Файл успешно удален:', response);

            if (response.success) {
                // Показываем сообщение об успехе
                showFileMessage('Файл "' + fileName + '" успешно удален', 'success');

                // Анимация удаления элемента
                $fileItem.fadeOut(300, function () {
                    $(this).remove();

                    // Обновляем список файлов
                    loadFilesList(dialogId);

                    // Если список пуст, показываем сообщение
                    var $filesList = $fileWindow.find('.save_file_spisok');
                    if ($filesList.children('.save_file_item').length === 0) {
                        $filesList.append('<li class="save_file_empty">файлов в диалоге нет</li>');
                    }
                });
            } else {
                showFileMessage('Ошибка удаления: ' + response.data, 'error');
                $button.html(originalHtml).prop('disabled', false);
            }
        },
        error: function (xhr, status, error) {
            console.log('❌ DEBUG: Ошибка AJAX при удалении:', error);
            showFileMessage('Ошибка удаления файла: ' + error, 'error');
            $button.html(originalHtml).prop('disabled', false);
        }
    });
});



// Обработчик кнопки редактирования имени файла
// 🔥 ОБРАБОТЧИК КНОПКИ РЕДАКТИРОВАНИЯ ИМЕНИ ФАЙЛА
$(document).on('click', '.file_edit_editor', function (e) {
    e.preventDefault();
    e.stopPropagation();

    var $button = $(this);
    var $fileItem = $button.closest('.save_file_item');
    var $fileNameText = $fileItem.find('.file-name-text');
    var $fileEditContainer = $fileItem.find('.file-name-edit');
    var $fileEditInput = $fileItem.find('.file-name-edit-input');
    var $saveButton = $fileItem.find('.file-name-save-btn');

    // 🔥 СОХРАНЯЕМ СТАРОЕ ИМЯ ДЛЯ СРАВНЕНИЯ ПРИ ПЕРЕИМЕНОВАНИИ
    var currentFileName = $fileNameText.text().trim();
    $fileNameText.data('old-name', currentFileName);
    $fileItem.data('old-name', currentFileName);

    // 🔥 ГАРАНТИРУЕМ ЧТО КНОПКА СОХРАНЕНИЯ ИМЕЕТ "✓"
    $saveButton.html('&#10004;').prop('disabled', false);

    // Показываем интерфейс редактирования
    $fileNameText.hide();
    $fileEditContainer.show();
    $fileEditInput.focus().select();
});

// 🔥 ОБРАБОТЧИК СОХРАНЕНИЯ ИМЕНИ ФАЙЛА С ПРОВЕРКОЙ
$(document).on('click', '.file-name-save-btn', function (e) {
    e.preventDefault();
    e.stopPropagation();

    var $button = $(this);
    var $fileItem = $button.closest('.save_file_item');
    var $fileEditInput = $fileItem.find('.file-name-edit-input');
    var $fileNameText = $fileItem.find('.file-name-text');
    var $fileEditContainer = $fileItem.find('.file-name-edit');
    var $fileDateElement = $fileItem.find('.file-date');
    var fileId = $fileItem.find('.file-delete').data('file-id');
    var newFileName = $fileEditInput.val().trim();

    if (!newFileName) {
        alert('Введите имя файла');
        return;
    }

    // Добавляем расширение .html если нет (только для проверки и отправки)
    var fileNameWithExt = newFileName;
    if (!fileNameWithExt.toLowerCase().endsWith('.html')) {
        fileNameWithExt += '.html';
    }

    // 🔥 ПРОВЕРЯЕМ СУЩЕСТВОВАНИЕ ФАЙЛА ПЕРЕД ОТПРАВКОЙ
    if (checkIfFileNameExistsInDialog(fileNameWithExt, $fileItem)) {
        alert('Файл с именем "' + fileNameWithExt + '" уже существует в этом диалоге!');
        $fileEditInput.focus().select();
        return;
    }

    console.log('✏️ DEBUG: Переименование файла:', { fileId, newFileName: fileNameWithExt });

    // Блокируем кнопку
    $button.html('...').prop('disabled', true);

    $.ajax({
        url: crm_ajax.ajaxurl,
        type: 'POST',
        data: {
            action: 'rename_file',
            file_id: fileId,
            new_file_name: fileNameWithExt
        },
        success: function (response) {
            console.log('✅ DEBUG: Файл переименован:', response);

            if (response.success) {
                // 🔥 ВАЖНО: ИСПОЛЬЗУЕМ ИМЯ БЕЗ РАСШИРЕНИЯ .html ДЛЯ ОТОБРАЖЕНИЯ
                var fileNameWithoutExt = response.data.file_name; // " (БЕЗ .html)
                var finalFileNameWithExt = fileNameWithoutExt + '.html';

                // 1. Обновляем текст имени (БЕЗ .html)
                $fileNameText.text(fileNameWithoutExt);

                // 2. 🔥 ОБНОВЛЯЕМ ВСЕ ССЫЛКИ С НОВЫМ ИМЕНЕМ ФАЙЛА
                var $downloadLink = $fileItem.find('.file-download');
                var oldUrl = $downloadLink.attr('data-file-url');

                // Заменяем только имя файла в URL, сохраняя путь
                var newUrl = oldUrl.replace(/[^\/]+\.html$/, finalFileNameWithExt);

                console.log('🔗 DEBUG: Обновляем URL:', { oldUrl, newUrl });

                // Обновляем ВСЕ атрибуты ссылки
                $downloadLink
                    .attr('data-file-url', newUrl)
                    .data('file-url', newUrl)
                    .attr('href', newUrl);

                // 3. 🔥 ОБНОВЛЯЕМ ВСЕ DATA-FILE-NAME АТРИБУТЫ (БЕЗ .html)
                $fileItem.find('[data-file-name]')
                    .attr('data-file-name', fileNameWithoutExt)
                    .data('file-name', fileNameWithoutExt);

                // 4. 🔥 ВАЖНО: ОБНОВЛЯЕМ REDACTOR_FILE И INPUT ЕСЛИ ЭТОТ ФАЙЛ СЕЙЧАС РЕДАКТИРУЕТСЯ
                var $fileWindow = $fileItem.closest('.file-creation-window');
                var $redactorInfo = $fileWindow.find('.redactor_file');
                var $fileNameInput = $fileWindow.find('.file-name-input');

                // Проверяем, редактируется ли сейчас этот файл
                var currentRedactorText = $redactorInfo.text().trim();
                var oldFileName = $fileItem.find('.file-name-text').data('old-name') ||
                    $fileNameText.data('old-name') ||
                    currentRedactorText.replace('редактируется ', '');

                if (currentRedactorText === 'редактируется ' + oldFileName) {
                    // 🔥 ОБНОВЛЯЕМ REDACTOR_FILE
                    $redactorInfo.text('редактируется ' + fileNameWithoutExt);

                    // 🔥 ОБНОВЛЯЕМ INPUT
                    $fileNameInput.val(fileNameWithoutExt);

                    console.log('🔄 DEBUG: Обновлен redactor_file и input для редактируемого файла');
                }

                // 5. ОБНОВЛЯЕМ ВРЕМЯ В ИНТЕРФЕЙСЕ
                var now = new Date();
                var newTime =
                    ('0' + now.getDate()).slice(-2) + '.' +
                    ('0' + (now.getMonth() + 1)).slice(-2) + '.' +
                    now.getFullYear() + ' ' +
                    ('0' + now.getHours()).slice(-2) + ':' +
                    ('0' + now.getMinutes()).slice(-2);

                $fileDateElement.text(newTime);

                // 🔥 ПОДСВЕЧИВАЕМ ВРЕМЯ ЖЕЛТЫМ НА 5 СЕКУНД
                $fileDateElement.css({
                    'color': '#31a060',
                });

                setTimeout(() => {
                    $fileDateElement.css({
                        'color': '',
                    });
                }, 5000);

                // 🔥 ВАЖНО: ВОССТАНАВЛИВАЕМ КНОПКУ "✓" ПРАВИЛЬНО
                // Сначала скрываем интерфейс редактирования
                $fileNameText.show();
                $fileEditContainer.hide();

                // 🔥 ГАРАНТИРУЕМ ЧТО КНОПКА "✓" БУДЕТ ПРИ СЛЕДУЮЩЕМ РЕДАКТИРОВАНИИ
                // Восстанавливаем HTML кнопки сохранения
                $button.html('&#10004;').prop('disabled', false);

                // Показываем уведомление
                showFileMessage('Файл переименован в "' + fileNameWithoutExt + '"', 'success');

            } else {
                alert('Ошибка: ' + response.data);
                // 🔥 ВОССТАНАВЛИВАЕМ КНОПКУ "✓" ПРИ ОШИБКЕ
                $button.html('&#10004;').prop('disabled', false);
            }
        },
        error: function (xhr, status, error) {
            console.log('❌ DEBUG: Ошибка переименования:', error);
            alert('Ошибка переименования: ' + error);
            // 🔥 ВОССТАНАВЛИВАЕМ КНОПКУ "✓" ПРИ ОШИБКЕ
            $button.html('&#10004;').prop('disabled', false);
        }
    });
});

// 🔥 ФУНКЦИЯ ПРОВЕРКИ СУЩЕСТВОВАНИЯ ИМЕНИ ФАЙЛА В ДИАЛОГЕ
function checkIfFileNameExistsInDialog(fileName, $currentFileItem) {
    var $fileWindow = $currentFileItem.closest('.file-creation-window');
    var $filesList = $fileWindow.find('.save_file_spisok');

    // Получаем текущее имя файла (которое редактируем)
    var currentOriginalName = $currentFileItem.find('.file-name-text').text().trim();

    // 🔥 НОРМАЛИЗУЕМ ИМЕНА: ЗАМЕНЯЕМ ПРОБЕЛЫ НА ДЕФИСЫ ДЛЯ СРАВНЕНИЯ
    var normalizeFileName = function (name) {
        // Заменяем пробелы на дефисы и приводим к нижнему регистру
        return name.replace(/\s+/g, '-').toLowerCase();
    };

    var currentNormalized = normalizeFileName(currentOriginalName);
    if (!currentNormalized.endsWith('.html')) {
        currentNormalized += '.html';
    }

    var fileExists = false;
    var targetFileName = normalizeFileName(fileName);
    if (!targetFileName.endsWith('.html')) {
        targetFileName += '.html';
    }

    $filesList.find('.save_file_item').each(function () {
        var $fileItem = $(this);

        // Пропускаем пустые элементы
        if ($fileItem.hasClass('save_file_empty')) {
            return true;
        }

        var existingFileName = $fileItem.find('.file-name-text').text().trim();

        // 🔥 НОРМАЛИЗУЕМ СУЩЕСТВУЮЩЕЕ ИМЯ ДЛЯ СРАВНЕНИЯ
        var existingNormalized = normalizeFileName(existingFileName);
        if (!existingNormalized.endsWith('.html')) {
            existingNormalized += '.html';
        }

        // 🔥 ИГНОРИРУЕМ ТЕКУЩИЙ ФАЙЛ (можно переименовать на то же имя)
        if (existingNormalized === currentNormalized) {
            return true;
        }

        // Проверяем совпадение НОРМАЛИЗОВАННЫХ имен
        if (existingNormalized === targetFileName) {
            fileExists = true;
            return false;
        }
    });

    console.log('🔍 DEBUG: Проверка имени файла:');
    console.log('- Исходное имя:', fileName);
    console.log('- Нормализованное:', targetFileName);
    console.log('- Текущее файл:', currentOriginalName);
    console.log('- Нормализованное текущее:', currentNormalized);
    console.log('- Файл существует:', fileExists);

    return fileExists;
}
// 🔥 ФУНКЦИЯ ДЛЯ ПОКАЗА СООБЩЕНИЙ
function showFileMessage(message, type) {
    // Создаем элемент сообщения
    var $message = $('<div class="file-message file-message-' + type + '" style="position: fixed; top: 20px; right: 20px; padding: 12px 20px; border-radius: 4px; z-index: 10000; color: white; font-weight: bold;">' + message + '</div>');

    // Устанавливаем цвет в зависимости от типа
    if (type === 'success') {
        $message.css('background-color', '#28a745');
    } else if (type === 'error') {
        $message.css('background-color', '#dc3545');
    } else {
        $message.css('background-color', '#17a2b8');
    }

    // Добавляем в тело документа
    $('body').append($message);

    // Показываем с анимацией
    $message.hide().fadeIn(300);

    // Автоматически скрываем через 5 секунд
    setTimeout(function () {
        $message.fadeOut(300, function () {
            $(this).remove();
        });
    }, 5000);
}


// 🔥 ПРОВЕРКА ПРИ ВВОДЕ ИМЕНИ (НА ЛЕТУ)
$(document).on('input', '.file-name-edit-input', function () {
    var $input = $(this);
    var $fileItem = $input.closest('.save_file_item');
    var $saveButton = $fileItem.find('.file-name-save-btn');
    var fileName = $input.val().trim();

    if (!fileName) {
        $saveButton.prop('disabled', false).css('opacity', '1');
        $input.css('border-color', '');
        return;
    }

    // 🔥 НОРМАЛИЗУЕМ ИМЯ ДЛЯ ПРОВЕРКИ
    var normalizeFileName = function (name) {
        return name.replace(/\s+/g, '-').toLowerCase();
    };

    var fileNameWithExt = normalizeFileName(fileName);
    if (!fileNameWithExt.endsWith('.html')) {
        fileNameWithExt += '.html';
    }

    // Проверяем существование
    if (checkIfFileNameExistsInDialog(fileNameWithExt, $fileItem)) {
        $saveButton.prop('disabled', true).css('opacity', '0.5');
        $input.css('border-color', '#ff4444');
    } else {
        $saveButton.prop('disabled', false).css('opacity', '1');
        $input.css('border-color', '#28a745');
    }
});

// Обработчик отмены редактирования
$(document).on('click', '.file-name-cancel-btn', function (e) {
    e.preventDefault();
    e.stopPropagation();

    var $fileItem = $(this).closest('.save_file_item');
    var $fileNameText = $fileItem.find('.file-name-text');
    var $fileEditContainer = $fileItem.find('.file-name-edit');

    // Скрываем интерфейс редактирования
    $fileEditContainer.hide();
    $fileNameText.show();
});