// ==================== ГЛОБАЛЬНЫЕ ПЕРЕМЕННЫЕ И ФУНКЦИИ ====================
let dialogsState = {};
let leadsDataCache = {};
let leadsData = {};



function initDialogsState(leadId) {
    if (!dialogsState[leadId]) {
        dialogsState[leadId] = {
            dialogs: [],
            currentDialogId: null
        };
    }
}


document.addEventListener('DOMContentLoaded', function () {
    const phoneLinks = document.querySelectorAll('.phone-link');

    phoneLinks.forEach(link => {
        const originalPhone = link.getAttribute('data-original-phone') || link.textContent;
        const formattedPhone = formatPhoneForLink(originalPhone);
        const displayPhone = formatPhoneDisplay(formattedPhone);

        link.href = 'tel:' + formattedPhone;
        link.textContent = displayPhone;
    });
});

function formatPhoneForLink(phone) {
    let cleaned = phone.replace(/\D/g, '');

    if (cleaned.startsWith('8') && cleaned.length === 11) {
        return '+7' + cleaned.substring(1);
    } else if (cleaned.startsWith('9') && cleaned.length === 10) {
        return '+7' + cleaned;
    } else if (cleaned.startsWith('7') && cleaned.length === 11) {
        return '+7' + cleaned.substring(1);
    }

    return '+' + cleaned;
}

function formatPhoneDisplay(phone) {
    const cleaned = phone.replace(/\D/g, '');

    if (cleaned.startsWith('7') && cleaned.length === 11) {
        return `+7 (${cleaned.substring(1, 4)}) ${cleaned.substring(4, 7)}-${cleaned.substring(7, 9)}-${cleaned.substring(9, 11)}`;
    }

    return phone;
}







// ✅ ПРОСТАЯ И НАДЕЖНАЯ ПРОВЕРКА
async function checkZayvNameRequired(leadId) {
    return new Promise((resolve) => {
        console.log('🔍 DEBUG: Проверка имени заявки через БД для lead:', leadId);

        $.ajax({
            url: crm_ajax.ajaxurl,
            type: 'POST',
            data: {
                action: 'get_lead_data',
                lead_id: leadId,
                nonce: crm_ajax.nonce
            },
            success: function (response) {
                if (response.success) {
                    // Просто проверяем есть ли любое значение в БД
                    const hasName = response.data.name_zayv &&
                        response.data.name_zayv.trim() !== '';

                    console.log('✅ DEBUG: Проверка имени заявки из БД:', {
                        name_zayv: response.data.name_zayv,
                        hasName: hasName
                    });

                    resolve(hasName);
                } else {
                    console.log('⚠️ DEBUG: Ошибка загрузки данных заявки');
                    resolve(false);
                }
            },
            error: function (xhr, status, error) {
                console.log('❌ DEBUG: Ошибка AJAX при проверке имени заявки:', error);
                resolve(false);
            }
        });
    });
}


function updateSenderEmail(leadId, dialogId, newSenderEmail) {
    console.log('🚀 DEBUG: updateSenderEmail', { leadId, dialogId, newSenderEmail });

    $.ajax({
        url: crm_ajax.ajaxurl,
        type: 'POST',
        data: {
            action: 'update_dialog_sender_email',
            lead_id: leadId,
            dialog_id: dialogId,
            sender_email: newSenderEmail,
            nonce: crm_ajax.nonce
        },
        success: function (response) {
            if (response.success) {
                console.log('✅ DEBUG: Sender email обновлен в БД');
                showNotification('Email отправителя обновлен', 'success');

                // Обновляем SMTP настройки без перезагрузки
                updateSmtpConfig(newSenderEmail);
            } else {
                console.log('❌ DEBUG: Ошибка сервера:', response.data);
                showNotification('Ошибка: ' + response.data, 'error');
            }
        },
        error: function (xhr, status, error) {
            console.log('❌ DEBUG: Ошибка AJAX:', error);
            showNotification('Ошибка сети: ' + error, 'error');
        }
    });
}



$(document).on('change', '.sender-email-select', function () {
    console.log('🔍 DEBUG: sender-email-select change event');
    console.log('🔍 DEBUG: Current value before change:', $(this).val());

    const $select = $(this);
    const leadId = $select.data('lead-id');
    const dialogId = $select.data('dialog-id');
    const newSenderEmail = $select.val();

    console.log('🔄 DEBUG: Смена отправителя', {
        leadId,
        dialogId,
        newSenderEmail,
        currentValue: $select.val()
    });

    // Меняем отображение сразу (оптимистичное обновление)
    $select.closest('.dialog-item').find('.sender-email-text').text(newSenderEmail);

    // 🔴 ДОБАВЛЕНО: Обновляем почту в КП без перезагрузки
    $('.avatar_mail.avatar_text').text(newSenderEmail);
    // Если КП в модальном окне с определенным ID
    $('#kp-sender-email').text(newSenderEmail);

    // Сохраняем в БД
    $.ajax({
        url: crm_ajax.ajaxurl,
        type: 'POST',
        data: {
            action: 'update_dialog_sender_email',
            lead_id: leadId,
            dialog_id: dialogId,
            sender_email: newSenderEmail,
            nonce: crm_ajax.nonce
        },
        success: function (response) {
            if (response.success) {
                console.log('✅ Sender email сохранен в БД');
                showNotification('Отправитель изменен на: ' + newSenderEmail, 'success');

                // Обновляем SMTP настройки для отправки писем
                updateSmtpConfig(newSenderEmail);

                // 🔴 ДОБАВЛЕНО: Дополнительное обновление КП после успешного сохранения
                $('.document-header .avatar_mail').text(newSenderEmail);
            } else {
                console.log('❌ Ошибка сервера:', response.data);
                showNotification('Ошибка сохранения', 'error');
                // Можно вернуть предыдущее значение при ошибке
            }
        },
        error: function (xhr, status, error) {
            console.log('❌ Ошибка AJAX:', error);
            showNotification('Ошибка сети', 'error');
        }
    });
});

// Функция обновления SMTP настроек
function updateSmtpConfig(senderEmail) {
    // Обновляем глобальные настройки для отправки писем
    if (typeof window.emailAccounts !== 'undefined') {
        window.currentSmtpConfig = {
            username: senderEmail,
            password: window.emailAccounts[senderEmail],
            from_email: senderEmail
        };
        console.log('🔧 SMTP настройки обновлены:', window.currentSmtpConfig);
    }
}

// При клике на сохранение
$('.save-sender-email-btn').click(function () {
    const $btn = $(this);
    const $select = $btn.prev('.sender-email-select');
    const leadId = $select.data('lead-id');
    const dialogId = $select.data('dialog-id');
    const newSenderEmail = $select.val();

    updateSenderEmail(leadId, dialogId, newSenderEmail);
    $btn.hide();
});

function getLeadEmail(leadId) {
    // Ищем email в DOM
    const $leadRow = $(`.lead-row[data-lead-id="${leadId}"]`);
    const $emailContainer = $leadRow.find('.email-edit-container');

    if ($emailContainer.length) {
        const $emailLink = $emailContainer.find('.email-link');
        if ($emailLink.length) {
            return $emailLink.text().trim();
        }

        const $emailInput = $emailContainer.find('.lead-email-input');
        if ($emailInput.length) {
            return $emailInput.val().trim();
        }
    }

    // Проверяем глобальные данные
    if (leadsData[leadId] && leadsData[leadId].email) {
        return leadsData[leadId].email;
    }

    return null;
}
async function renderDialogs(leadId) {
    console.log('🔄 DEBUG: renderDialogs вызван для lead:', leadId);

    const dialogsList = document.getElementById('dialogsList-' + leadId);
    if (!dialogsList) {
        console.error('❌ DEBUG: Не найден dialogsList-' + leadId);
        return;
    }

    const dialogs = dialogsState[leadId].dialogs;
    const currentDialogId = dialogsState[leadId].currentDialogId;
    const existingDialogs = dialogsList.querySelectorAll('.dialog-item');

    // СОРТИРОВКА: новые первыми
    dialogs.sort((a, b) => {
        const dateA = new Date(a.created_at || a.date_created || a.timestamp);
        const dateB = new Date(b.created_at || b.date_created || b.timestamp);
        return dateB - dateA; // Новые первыми
    });

    // ПРОВЕРЯЕМ, ЕСТЬ ЛИ НОВЫЕ ДИАЛОГИ ДЛЯ ДОБАВЛЕНИЯ
    const newDialogs = dialogs.filter(dialog => {
        const dialogId = parseInt(dialog.id);
        return !Array.from(existingDialogs).find(existing =>
            parseInt(existing.dataset.dialogId) === dialogId
        );
    });

    if (newDialogs.length > 0) {
        // ДОБАВЛЯЕМ ТОЛЬКО НОВЫЕ ДИАЛОГИ
        console.log('🆕 DEBUG: Добавляем новые диалоги:', newDialogs.length);

        const htmlPromises = newDialogs.map(async (dialog) => {
            const numericDialogId = parseInt(dialog.id);
            const numericCurrentId = currentDialogId ? parseInt(currentDialogId) : null;
            const isActive = numericDialogId === numericCurrentId;

            try {
                const response = await $.ajax({
                    url: crm_ajax.ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'get_dialog_item_html',
                        lead_id: leadId,
                        dialog_id: dialog.id,
                        dialog_name: dialog.name,
                        dialog_email: dialog.email || '',
                        dialog_created_at: dialog.created_at || '',
                        is_active: isActive
                    }
                });

                if (response.success) {
                    return { html: response.data, dialogId: dialog.id };
                }
            } catch (error) {
                console.error('❌ Ошибка загрузки HTML диалога:', error);
            }
            return null;
        });

        const htmlResults = await Promise.all(htmlPromises);

        // ⭐ ИСПРАВЛЕНИЕ: Добавляем новые диалоги в соответствии с сортировкой
        // Для этого нужно определить правильную позицию для каждого нового диалога
        htmlResults.forEach(result => {
            if (result && result.html) {
                // Находим индекс этого диалога в отсортированном массиве
                const dialogIndex = dialogs.findIndex(d => parseInt(d.id) === parseInt(result.dialogId));

                if (dialogIndex === 0) {
                    // Если это самый новый диалог - добавляем в самое начало
                    dialogsList.insertAdjacentHTML('afterbegin', result.html);
                } else {
                    // Ищем, после какого существующего диалога нужно вставить
                    let insertAfterElement = null;

                    // Ищем ближайший более старый диалог, который уже есть в списке
                    for (let i = dialogIndex - 1; i >= 0; i--) {
                        const olderDialogId = parseInt(dialogs[i].id);
                        const olderElement = dialogsList.querySelector(`[data-dialog-id="${olderDialogId}"]`);
                        if (olderElement) {
                            insertAfterElement = olderElement;
                            break;
                        }
                    }

                    if (insertAfterElement) {
                        // Вставляем после найденного элемента
                        insertAfterElement.insertAdjacentHTML('afterend', result.html);
                    } else {
                        // Если не нашли подходящий элемент - добавляем в начало
                        dialogsList.insertAdjacentHTML('afterbegin', result.html);
                    }
                }

                console.log('✅ DEBUG: Добавлен новый диалог:', result.dialogId);
            }
        });

        // ОБНОВЛЯЕМ АКТИВНЫЙ ДИАЛОГ
        updateActiveDialogOnly(leadId);

    } else if (existingDialogs.length > 0) {
        // НЕТ НОВЫХ ДИАЛОГОВ - ПРОСТО ОБНОВЛЯЕМ АКТИВНЫЙ
        console.log('✅ DEBUG: Нет новых диалогов, обновляем активный');
        updateActiveDialogOnly(leadId);
    } else {
        // ПЕРВИЧНАЯ ЗАГРУЗКА - ЗАГРУЖАЕМ ВСЕ ДИАЛОГИ
        console.log('🔍 DEBUG: Первичная загрузка диалогов:', dialogs.length);
        dialogsList.innerHTML = '';

        const htmlPromises = dialogs.map(async (dialog) => {
            const numericDialogId = parseInt(dialog.id);
            const numericCurrentId = currentDialogId ? parseInt(currentDialogId) : null;
            const isActive = numericDialogId === numericCurrentId;

            try {
                const response = await $.ajax({
                    url: crm_ajax.ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'get_dialog_item_html',
                        lead_id: leadId,
                        dialog_id: dialog.id,
                        dialog_name: dialog.name,
                        dialog_email: dialog.email || '',
                        dialog_created_at: dialog.created_at || '',
                        is_active: isActive
                    }
                });

                if (response.success) {
                    return response.data;
                }
            } catch (error) {
                console.error('❌ Ошибка загрузки HTML диалога:', error);
            }
            return '';
        });

        const htmlResults = await Promise.all(htmlPromises);

        // ⭐ ИСПРАВЛЕНИЕ: добавляем в правильном порядке (новые первыми)
        htmlResults.forEach(html => {
            if (html) {
                dialogsList.insertAdjacentHTML('afterbegin', html);
            }
        });

        console.log('✅ DEBUG: Все диалоги загружены');

        // ЕСЛИ ЕСТЬ АКТИВНЫЙ ДИАЛОГ - ЗАГРУЖАЕМ ЕГО СООБЩЕНИЯ
        if (currentDialogId) {
            loadMessageSectionForDialog(leadId, currentDialogId);
        }
    }
}



// ОБНОВЛЕННАЯ ФУНКЦИЯ: обновляет только активный диалог БЕЗ перезагрузки
function updateActiveDialogOnly(leadId) {
    const currentDialogId = dialogsState[leadId].currentDialogId;
    const dialogsList = document.getElementById('dialogsList-' + leadId);

    if (!dialogsList) return;

    // 1. УБИРАЕМ АКТИВНЫЙ КЛАСС У ВСЕХ И УДАЛЯЕМ СЕКЦИИ СООБЩЕНИЙ
    const allDialogs = dialogsList.querySelectorAll('.dialog-item');
    allDialogs.forEach(dialog => {
        dialog.classList.remove('active');

        // Убираем индикатор "ОТКРЫТ"
        const indicator = dialog.querySelector('.active-dialog-indicator');
        if (indicator) indicator.remove();

        // Меняем стрелочку ▼
        const arrow = dialog.querySelector('.dialog-arrow');
        if (arrow) arrow.textContent = '▼';

        // ВАЖНО: УДАЛЯЕМ СЕКЦИЮ СООБЩЕНИЙ ПРИ ЗАКРЫТИИ
        const messageSection = dialog.querySelector('.message-section');
        if (messageSection) {
            messageSection.remove();
            console.log('🗑️ DEBUG: Удалена секция сообщений для диалога:', dialog.dataset.dialogId);
        }
    });

    // 2. ЕСЛИ ЕСТЬ АКТИВНЫЙ ДИАЛОГ - ДЕЛАЕМ ЕГО АКТИВНЫМ
    if (currentDialogId) {
        const activeDialog = dialogsList.querySelector(`.dialog-item[data-dialog-id="${currentDialogId}"]`);
        if (activeDialog) {
            activeDialog.classList.add('active');

            // Добавляем индикатор "ОТКРЫТ"
            const strong = activeDialog.querySelector('strong');
            if (strong && !strong.querySelector('.active-dialog-indicator')) {
                strong.insertAdjacentHTML('beforeend', '<span class="active-dialog-indicator">ОТКРЫТ</span>');
            }

            // Меняем стрелочку ▲
            const arrow = activeDialog.querySelector('.dialog-arrow');
            if (arrow) arrow.textContent = '▲';

            // ЗАГРУЖАЕМ СООБЩЕНИЯ ЕСЛИ ИХ НЕТ
            const existingForm = activeDialog.querySelector('.message-section');
            if (!existingForm) {
                loadMessageSectionForDialog(leadId, currentDialogId);
            }
        }
    }

    console.log('✅ DEBUG: Активный диалог обновлен:', currentDialogId);
}

// ФУНКЦИЯ ДЛЯ ЗАГРУЗКИ СЕКЦИИ СООБЩЕНИЙ
async function loadMessageSectionForDialog(leadId, dialogId) {
    console.log('🔍 DEBUG: Загружаем секцию сообщений для диалога:', dialogId);

    const dialog = dialogsState[leadId].dialogs.find(d => parseInt(d.id) === parseInt(dialogId));
    if (!dialog) return;

    const dialogElement = document.querySelector(`.dialog-item[data-dialog-id="${dialogId}"]`);
    if (!dialogElement) return;

    try {
        const messageHtml = await loadDialogMessageSection(leadId, dialog);
        if (messageHtml && messageHtml.trim() !== '') {
            dialogElement.insertAdjacentHTML('beforeend', messageHtml);
            console.log('✅ DEBUG: Секция сообщений добавлена');

            if (window.reinitializeDialogEditors) {
                window.reinitializeDialogEditors(dialogElement);
            }
        }
    } catch (error) {
        console.error('❌ Ошибка загрузки секции сообщений:', error);
    }
}


// Функция для загрузки HTML из PHP файла
async function loadDialogMessageSection(leadId, dialog) {
    console.log('=== ЗАГРУЗКА ЧЕРЕЗ AJAX ===');

    try {
        const response = await fetch(crm_ajax.ajaxurl, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: new URLSearchParams({
                action: 'get_dialog_template',
                lead_id: leadId,
                dialog_id: dialog.id,
                dialog_name: dialog.name,
                dialog_email: dialog.email || '',
                dialog_created_at: dialog.created_at || '',
                // nonce: crm_ajax.nonce
            })
        });

        console.log('AJAX статус:', response.status);

        const responseText = await response.text();
        // console.log('Ответ сервера:', responseText);

        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }

        let data;
        try {
            data = JSON.parse(responseText);
        } catch (e) {
            console.error('Ошибка парсинга JSON:', e);
            throw new Error('Invalid JSON response');
        }

        if (data.success) {
            console.log('AJAX HTML загружен успешно');
            return data.data;
        } else {
            throw new Error('AJAX returned error: ' + (data.data || 'Unknown error'));
        }

    } catch (error) {
        console.error('Ошибка AJAX:', error);
        //  ВОЗВРАЩАЕМ ПУСТУЮ СТРОКУ ВМЕСТО FALLBACK
        return '';
    }
}

// ==================== НАЧАЛО jQuery ====================
jQuery(document).ready(function ($) {
    // ==================== ОТКРЫТИЕ/ЗАКРЫТИЕ ПАНЕЛИ ДИАЛОГА ====================
    $(document).on('click', '.toggle-dialog', function (e) {
        e.stopPropagation();
        var $button = $(this);
        var leadId = $button.data('lead-id');
        var $leadRow = $button.closest('.lead-row');
        var $dialogRow = $('#dialog-row-' + leadId);
        var $dialogPanel = $('#dialog-panel-' + leadId);

        // ⭐ ЗАКРЫВАЕМ ВСЕ ДИАЛОГИ ВО ВСЕХ ЗАЯВКАХ ПРИ ЛЮБОМ КЛИКЕ
        $('.dialog-item.active').removeClass('active');
        // Сбрасываем состояние во всех заявках
        for (let leadId in dialogsState) {
            if (dialogsState[leadId]) {
                dialogsState[leadId].currentDialogId = null;
            }
        }

        // Закрываем все другие открытые панели
        $('.dialog-row[aria-hidden="false"]').not($dialogRow).each(function () {
            var $otherRow = $(this);
            var $otherPanel = $otherRow.find('.dialog-panel');
            $otherPanel.attr('aria-hidden', 'true');
            setTimeout(() => {
                $otherRow.attr('aria-hidden', 'true');
            }, 300);
        });

        $('.toggle-dialog').not($button).attr('aria-expanded', 'false').removeClass('active');
        $('.lead-row').not($leadRow).removeClass('expanded');

        // Переключаем текущую панель
        if ($dialogRow.attr('aria-hidden') === 'false') {
            // Закрываем заявку
            $dialogPanel.attr('aria-hidden', 'true');
            setTimeout(() => {
                $dialogRow.attr('aria-hidden', 'true');
            }, 300);
            $button.attr('aria-expanded', 'false').removeClass('active');
            $leadRow.removeClass('expanded');
        } else {
            // Открываем
            $dialogRow.attr('aria-hidden', 'false');
            setTimeout(() => {
                $dialogPanel.attr('aria-hidden', 'false');
            }, 10);
            $button.attr('aria-expanded', 'true').addClass('active');
            $leadRow.addClass('expanded');

            // Загружаем диалоги если это первое открытие
            loadDialogsForLead(leadId);
        }
    });


    // создание заявки по кнопке
    jQuery(document).ready(function ($) {
        // Обработчик отправки формы
        $('#create_zayv_form').on('submit', function (e) {
            e.preventDefault();

            const form = $(this);
            const submitBtn = form.find('.create_zayv');

            // Получаем данные формы КАК ЕСТЬ (с маской)
            const zayvName = $('#zayv_name').val().trim();
            const clientName = $('#client_name').val().trim();
            const clientPhone = $('#client_phone').val().trim(); // Берем с маской!
            // Простая валидация
            if (!zayvName || !clientName || !clientPhone) {
                showMessage_zayv('Все поля обязательны для заполнения', 'error');
                return;
            }

            // Второй блок с форматированием (если это важно)
            const phoneNumbers = clientPhone.replace(/\D/g, '');

            if (!isValidPhone(phoneNumbers)) {
                showMessage_zayv('❌ Введите корректный номер телефона ( 10 цифр после +7)', 'error');
                $('#client_phone').focus();
                return;
            }


            // Форматируем телефон
            const formattedPhone = formatPhoneOnSave(clientPhone);

            // Блокируем кнопку
            submitBtn.prop('disabled', true).text('Создание...');

            // Отправляем AJAX запрос
            $.ajax({
                url: crm_ajax.ajaxurl,
                type: 'POST',
                data: {
                    action: 'create_manual_lead',
                    // nonce: crm_ajax.nonce,
                    zayv_name: zayvName,
                    client_name: clientName,
                    client_phone: clientPhone // Отправляем с маской!
                },
                success: function (response) {
                    if (response.success) {
                        showMessage_zayv('✅ Заявка успешно создана! ID: ' + response.data.lead_id, 'success');
                        form[0].reset();

                        // Обновляем страницу через 2 секунды
                        setTimeout(() => {
                            location.reload();
                        }, 2000);

                    } else {
                        showMessage_zayv('❌ Ошибка: ' + response.data, 'error');
                    }
                },
                error: function () {
                    showMessage_zayv('❌ Ошибка соединения с сервером', 'error');
                },
                complete: function () {
                    submitBtn.prop('disabled', false).text('Создать заявку');
                }
            });
        });

        function showMessage_zayv(text, type) {
            const messageDiv = $('#create_zayv_message');
            messageDiv.removeClass('success error')
                .addClass(type)
                .text(text)
                .show();

            setTimeout(() => {
                messageDiv.fadeOut();
            }, 5000);
        }

        // Функция создания заявки
        function createManualLead() {
            const form = $('#create_zayv_form');
            const submitBtn = form.find('.create_zayv');
            const messageDiv = $('#create_zayv_message');

            // Получаем данные формы
            const zayvName = $('#zayv_name').val().trim();
            const clientName = $('#client_name').val().trim();
            const clientPhone = $('#client_phone').val().trim();

            // Валидация
            if (!zayvName) {
                showMessage_zayv('Введите имя заявки', 'error');
                $('#zayv_name').focus();
                return;
            }

            if (!clientName) {
                showMessage_zayv('Введите имя клиента', 'error');
                $('#client_name').focus();
                return;
            }

            if (!clientPhone) {
                showMessage_zayv('Введите телефон клиента', 'error');
                $('#client_phone').focus();
                return;
            }

            let phoneNumbers = clientPhone.replace(/\D/g, '');

            // Проверяем валидность телефона
            if (!isValidPhone(phoneNumbers)) {
                showMessage_zayv('❌ Введите корректный номер телефона (еще 10 цифр после +7)', 'error');
                $('#client_phone').focus();
                return;
            }

            // Форматируем телефон перед отправкой
            clientPhone = formatPhoneOnSave(clientPhone);


            // Блокируем кнопку
            submitBtn.prop('disabled', true).text('Создание...');

            // Отправляем AJAX запрос
            $.ajax({
                url: crm_ajax.ajaxurl,
                type: 'POST',
                data: {
                    action: 'create_manual_lead',
                    nonce: crm_ajax.nonce,
                    zayv_name: zayvName,
                    client_name: clientName,
                    client_phone: clientPhone
                },
                success: function (response) {
                    if (response.success) {
                        showMessage_zayv('✅ Заявка успешно создана! ID: ' + response.data.lead_id, 'success');
                        form[0].reset(); // Очищаем форму

                        // Обновляем список заявок через 2 секунды
                        setTimeout(() => {
                            refreshLeadsList();
                        }, 2000);

                    } else {
                        showMessage_zayv('❌ Ошибка: ' + response.data, 'error');
                    }
                },
                error: function (xhr, status, error) {
                    showMessage_zayv('❌ Ошибка соединения с сервером', 'error');
                    console.error('AJAX Error:', error);
                },
                complete: function () {
                    submitBtn.prop('disabled', false).text('Создать заявку');
                }
            });
        }

        $('#client_phone').on('input', function () {
            let value = $(this).val().replace(/\D/g, '');

            if (value.length > 1) {
                // Просто добавляем +7 в начало если его нет
                if (!value.startsWith('7') && !value.startsWith('8')) {
                    value = '7' + value;
                }

                // Базовая маска
                let formatted = '+7 (' + value.substring(1, 4) + ') ' + value.substring(4, 7) + '-' + value.substring(7, 9) + '-' + value.substring(9, 11);
                $(this).val(formatted);
            }
        });



        // Функция обновления списка заявок
        function refreshLeadsList() {
            // Если у вас есть функция для загрузки заявок, вызовите её здесь
            if (typeof loadLeads === 'function') {
                loadLeads();
            } else {
                // Или просто перезагружаем страницу
                location.reload();
            }
        }

        // Дополнительные обработчики для улучшения UX
        $('#zayv_name, #client_name, #client_phone').on('keypress', function (e) {
            // Enter в любом поле формы отправляет форму
            if (e.which === 13) {
                e.preventDefault();
                createManualLead();
            }
        });

        // Автофокус на первое поле при загрузке
        $('#zayv_name').focus();
    });


    $(document).on('click', '.send-message-with-files-dialog', async function (e) {
        e.preventDefault();
        e.stopPropagation();

        console.log('🔄 ========== START SEND MESSAGE PROCESS ==========');

        var leadId = $(this).data('lead-id');
        var dialogId = $(this).data('dialog-id');
        console.log('🔍 DEBUG: Button clicked with data:', { leadId: leadId, dialogId: dialogId });

        var $panel = $('#dialog-panel-' + leadId);
        var $activeDialog = $panel.find('.dialog-item.active');
        console.log('🔍 DEBUG: Found elements:', {
            panel: $panel.length,
            activeDialog: $activeDialog.length,
            activeDialogHTML: $activeDialog.html() ? 'exists' : 'empty'
        });

        // Берем ПОСЛЕДНИЕ поля
        var $recipientEmail = $activeDialog.find('.recipient-email').last();
        var $messageText = $activeDialog.find('.message-text').last();
        var $attachmentsContainer = $activeDialog.find('.attachments-container').last();
        var $attachmentsList = $attachmentsContainer.find('.attachments-list').last();
        var $attachments = $attachmentsList.find('.attachment-item');
        var $statusDiv = $activeDialog.find('.message-status').last();

        console.log('🔍 DEBUG: Form elements found:', {
            recipientEmail: $recipientEmail.length,
            messageText: $messageText.length,
            attachmentsContainer: $attachmentsContainer.length,
            attachmentsList: $attachmentsList.length,
            attachments: $attachments.length,
            statusDiv: $statusDiv.length
        });

        var recipientEmails = $recipientEmail.val().trim();
        var messageText = $messageText.val().trim();

        console.log('📝 DEBUG: Form values:', {
            recipientEmails: recipientEmails,
            messageText: messageText,
            messageTextLength: messageText.length
        });

        // Валидация
        if (!dialogId) {
            console.log('❌ DEBUG: Dialog ID is missing');
            showNotification('Выберите диалог', 'error');
            return;
        }

        if (!recipientEmails) {
            console.log('❌ DEBUG: Recipient emails are empty');
            showNotification('Введите email получателя', 'error');
            return;
        }

        // Разделяем email через запятую
        var emailArray = recipientEmails.split(',').map(function (email) {
            return email.trim();
        }).filter(function (email) {
            return email !== '';
        });

        console.log('📧 DEBUG: Parsed email array:', emailArray);

        // Валидация каждого email
        var emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        var invalidEmails = emailArray.filter(function (email) {
            return !emailRegex.test(email);
        });

        if (invalidEmails.length > 0) {
            console.log('❌ DEBUG: Invalid emails found:', invalidEmails);
            showNotification('Некорректные email адреса: ' + invalidEmails.join(', '), 'error');
            return;
        }

        if (emailArray.length === 0) {
            console.log('❌ DEBUG: No valid email addresses after filtering');
            showNotification('Введите хотя бы один корректный email', 'error');
            return;
        }

        console.log('✅ DEBUG: Email validation passed');

        // Получаем HTML редактора
        var editorHtml = '';
        var $editor = $('.file-content-editor');

        if ($editor.length) {
            editorHtml = $editor[0].outerHTML;
            console.log('📄 DEBUG: Editor HTML captured, length:', editorHtml.length);
            console.log('🔍 DEBUG: Editor HTML sample:', editorHtml.substring(0, 300) + '...');
        } else {
            console.log('⚠️ DEBUG: Editor not found, using fallback');
            editorHtml = '<div class="file-content-editor">' + (messageText || 'Сообщение') + '</div>';
        }

        // ПРОВЕРКА НАЛИЧИЯ ИМЕНИ ЗАЯВКИ
        try {
            console.log('🔍 DEBUG: Checking zayv name for lead:', leadId);
            const hasZayvName = await checkZayvNameRequired(leadId);
            console.log('✅ DEBUG: Zayv name check result:', hasZayvName);

            if (!hasZayvName) {
                console.log('❌ DEBUG: Zayv name is missing');
                showNotification('Нельзя отправить письмо без имени заявки. Укажите имя заявки в таблице.', 'error');
                return;
            }
        } catch (error) {
            console.log('❌ DEBUG: Zayv name check error:', error);
            showNotification('Ошибка проверки имени заявки', 'error');
            return;
        }

        // ПОЛУЧАЕМ ТЕМУ ПИСЬМА
        console.log('🔍 DEBUG: Getting subject for lead:', leadId, 'dialog:', dialogId);
        var subject = await getDialogSubject(leadId, dialogId);
        console.log('📝 DEBUG: Generated subject:', subject);

        // Собираем информацию о файлах
        var attachments = [];
        $attachments.each(function (index) {
            var $item = $(this);
            var fileData = {
                url: $item.data('file-url'),
                name: $item.data('file-name')
            };
            console.log('📎 DEBUG: Attachment ' + index + ':', fileData);
            attachments.push(fileData);
        });

        console.log('📦 DEBUG: Total attachments:', attachments.length);

        // Показываем индикатор загрузки
        var $button = $(this);
        var originalText = $button.text();
        $button.text('Отправка...').prop('disabled', true);
        $statusDiv.html('<div class="notice notice-info">Отправка сообщения с файлами на ' + emailArray.length + ' email...</div>');

        // Подготавливаем данные для AJAX
        var ajaxData = {
            action: 'send_message_with_files',
            dialog_id: dialogId,
            recipient_emails: emailArray,
            message_text: messageText,
            attachments: attachments,
            subject: subject,
            editor_html: editorHtml
        };

        console.log('🚀 ========== SENDING AJAX REQUEST ==========');
        console.log('📤 DEBUG: Email array:', emailArray);
        console.log('📤 DEBUG: Email array is array:', Array.isArray(emailArray));
        console.log('📤 DEBUG: Attachments details:', {
            count: attachments.length,
            attachments: attachments,
            isArray: Array.isArray(attachments)
        });

        // Отправляем AJAX запрос
        $.ajax({
            url: crm_ajax.ajaxurl,
            type: 'POST',
            data: ajaxData,
            traditional: false,
            timeout: 30000,
            success: function (response) {
                console.log('✅ ========== AJAX SUCCESS ==========');
                console.log('📨 DEBUG: Full AJAX response:', response);

                if (response.success) {
                    var messageIds = response.data.message_ids || {};
                    var sentCount = response.data.sent_count || 0;
                    var totalCount = response.data.total_count || 0;

                    console.log('🎉 DEBUG: Message sent successfully!');
                    console.log('📊 DEBUG: Statistics:', {
                        messageIds: messageIds,
                        sentCount: sentCount,
                        totalCount: totalCount,
                        emailSent: response.data.email_sent,
                        recipientEmails: emailArray
                    });

                    if (response.data.email_sent) {
                        showNotification('Сообщение отправлено с ' + attachments.length + ' файлом(ами) на ' + sentCount + ' из ' + totalCount + ' email', 'success');
                    } else {
                        showNotification('Сообщение сохранено с ' + attachments.length + ' файлом(ами), но не отправлено по email', 'warning');
                    }

                    // Очищаем форму
                    $attachmentsList.empty();
                    $messageText.val('');

                    // Логируем детальные результаты
                    if (response.data.results) {
                        console.log('📧 DEBUG: Detailed email results:', response.data.results);
                        // Выведем информацию о каждом email
                        for (var email in response.data.results) {
                            console.log('📧 Email ' + email + ': ' + JSON.stringify(response.data.results[email]));
                        }
                    }

                } else {
                    console.log('❌ ========== AJAX SUCCESS BUT OPERATION FAILED ==========');
                    console.log('🔍 DEBUG: Error response data:', response.data);

                    var errorMessage = 'Ошибка отправки сообщения';
                    if (response.data && typeof response.data === 'object') {
                        if (response.data.message) {
                            errorMessage = response.data.message;
                        }
                        if (response.data.debug) {
                            console.log('🐛 DEBUG: Server debug info:', response.data.debug);
                        }
                        if (response.data.results) {
                            console.log('📧 DEBUG: Email-specific errors:', response.data.results);
                            var emailErrors = [];
                            for (var email in response.data.results) {
                                if (response.data.results[email].error) {
                                    emailErrors.push(email + ': ' + response.data.results[email].error);
                                }
                            }
                            if (emailErrors.length > 0) {
                                errorMessage += '\n' + emailErrors.join('\n');
                            }
                        }
                    }

                    showNotification(errorMessage, 'error');
                }
            },
            error: function (xhr, status, error) {
                console.log('❌ ========== AJAX ERROR ==========');
                console.log('🔍 DEBUG: Full error details:');
                console.log('   Status:', status);
                console.log('   Error:', error);
                console.log('   Status Code:', xhr.status);
                console.log('   Status Text:', xhr.statusText);
                console.log('   Response Text:', xhr.responseText);

                // Детальный анализ ошибки
                var errorDetails = 'Ошибка отправки: ';

                if (xhr.status === 0) {
                    errorDetails += 'Нет соединения с сервером';
                } else if (xhr.status === 404) {
                    errorDetails += 'Страница не найдена (404)';
                } else if (xhr.status === 500) {
                    errorDetails += 'Внутренняя ошибка сервера (500)';
                    console.log('🐛 DEBUG: Server returned 500 - checking for PHP errors...');

                    // Пытаемся найти PHP ошибку в ответе
                    if (xhr.responseText) {
                        console.log('🐛 DEBUG: Full response text:', xhr.responseText);
                        var phpErrorMatch = xhr.responseText.match(/<b>([^<]+)<\/b>/);
                        if (phpErrorMatch) {
                            errorDetails += ': ' + phpErrorMatch[1];
                        }
                    }
                } else {
                    errorDetails += 'Код ошибки: ' + xhr.status;
                }

                // Пытаемся распарсить JSON ошибки
                try {
                    if (xhr.responseText) {
                        var errorResponse = JSON.parse(xhr.responseText);
                        console.log('📋 DEBUG: Parsed error response:', errorResponse);
                        if (errorResponse.data && errorResponse.data.message) {
                            errorDetails = errorResponse.data.message;
                        } else if (errorResponse.message) {
                            errorDetails = errorResponse.message;
                        }
                    }
                } catch (e) {
                    console.log('❌ DEBUG: Could not parse error response as JSON');
                }

                showNotification(errorDetails, 'error');
            },
            complete: function () {
                console.log('🔚 ========== AJAX COMPLETE ==========');
                $button.text(originalText).prop('disabled', false);
            }
        });
    });
    // ==================== СОЗДАНИЕ ФАЙЛА (ВНУТРИ ДИАЛОГА) ====================
    // Открытие/закрытие окна создания файла (делегирование)
    // Простой обработчик для тестирования



    $(document).on('click', '.create-file-btn-dialog', function (e) {
        e.preventDefault();
        e.stopPropagation();


        var $button = $(this);
        var leadId = $(this).data('lead-id');
        var dialogId = $(this).data('dialog-id');


        // ==================== ИНДИКАТОР ЗАГРУЗКИ ====================
        // Добавляем span с многоточием, а не меняем весь текст
        $button.prop('disabled', true);
        if (!$button.find('.loading-dots').length) {
            $button.append('<span class="loading-dots">...</span>');
        }

        // ==================== AJAX ПРОВЕРКА ЛИЦЕНЗИИ ====================
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'crm_check_license_ajax',
            },
            success: function (response) {

                $button.find('.loading-dots').remove();
                $button.prop('disabled', false);
                if (response.success && response.data.active) {
                    executeFileCreation(leadId, dialogId, $button);
                } else {
                    // Спрашиваем пользователя
                    var payPageUrl = response.data.pay_url || '';

                    if (payPageUrl) {
                        var userConfirm = confirm(
                            '🔒 Эта функция доступна только в PRO/VIP версии!\n\n' +
                            'Хотите перейти на страницу оплаты?'
                        );

                        if (userConfirm) {
                            window.open(payPageUrl, '_blank');
                        }
                    } else {
                        $button.find('.loading-dots').remove();
                        $button.prop('disabled', false);
                        alert('🔒 Эта функция доступна только в PRO/VIP версии!');
                    }
                }
            },
            error: function () {
                $button.find('.loading-dots').remove();
                $button.prop('disabled', false);
                alert('Ошибка проверки лицензии. Попробуйте позже.');
            },
        });
    });

    function executeFileCreation(leadId, dialogId, $button) {
        console.log('Клик по созданию файла:', leadId, dialogId);

        // Способ 1: Ищем окно внутри диалога
        var $dialogItem = $button.closest('.dialog-item');
        var $fileWindow = $dialogItem.find('.file-creation-window');

        console.log('Способ 1 - найдено окон в диалоге:', $fileWindow.length);

        // Способ 2: Если не нашли, ищем по ID
        if ($fileWindow.length === 0) {
            $fileWindow = $('#file-window-' + leadId + '-' + dialogId);
            console.log('Способ 2 - найдено окон по ID:', $fileWindow.length);
        }

        // Способ 3: Если все еще не нашли, ищем во всем документе
        if ($fileWindow.length === 0) {
            $fileWindow = $('.file-creation-window');
            console.log('Способ 3 - всего окон в документе:', $fileWindow.length);
        }

        if ($fileWindow.length === 0) {
            console.error('Окно не найдено!');
            console.log('Текущий HTML диалога:', $dialogItem.html());
            return;
        }

        // Переключаем видимость окна
        if ($fileWindow.is(':visible')) {
            $fileWindow.slideUp(300);
            console.log('Закрываем окно файла');
        } else {
            $fileWindow.slideDown(300);
            console.log('Открываем окно файла');
        }
    }

    // ==================== ВСПОМОГАТЕЛЬНЫЕ ФУНКЦИИ ДЛЯ ДИАЛОГОВ ====================



    // Функция для получения полной темы письма
    async function getDialogSubject(leadId, dialogId) {
        console.log('🔍 DEBUG: Формирование темы письма:', { leadId, dialogId });

        try {
            const zayvName = await getZayvName(leadId);
            console.log('🏷️ DEBUG: Имя заявки:', zayvName);

            const clientName = getClientName(leadId);
            console.log('👤 DEBUG: Имя клиента:', clientName);

            const dialogName = getDialogName(leadId, dialogId);
            console.log('💬 DEBUG: Название диалога:', dialogName);

            const subjectParts = [];
            if (zayvName && zayvName !== '') subjectParts.push(zayvName);
            if (clientName && clientName !== '') subjectParts.push(clientName);
            subjectParts.push(dialogName);

            const subject = subjectParts.join('; ');
            console.log('📝 DEBUG: Сформированная тема:', subject);

            return subject;
        } catch (error) {
            console.log('❌ DEBUG: Ошибка формирования темы:', error);
            return getDialogName(leadId, dialogId);
        }
    }

    // Функция для получения имени заявки
    // ✅ ОБНОВИТЬ эту функцию
    function getZayvName(leadId) {
        return new Promise((resolve) => {
            console.log('🔍 DEBUG: Получение имени заявки из БД для lead:', leadId);

            $.ajax({
                url: crm_ajax.ajaxurl,
                type: 'POST',
                data: {
                    action: 'get_lead_data',
                    lead_id: leadId,
                    nonce: crm_ajax.nonce
                },
                success: function (response) {
                    if (response.success) {
                        const nameZayv = response.data.name_zayv &&
                            response.data.name_zayv.trim() !== '' &&
                            response.data.name_zayv !== 'Не указан'
                            ? response.data.name_zayv : '';

                        console.log('✅ DEBUG: Имя заявки из БД для темы:', nameZayv);
                        resolve(nameZayv);
                    } else {
                        console.log('⚠️ DEBUG: Ошибка загрузки данных заявки');
                        resolve('');
                    }
                },
                error: function (xhr, status, error) {
                    console.log('❌ DEBUG: Ошибка AJAX при загрузке имени заявки:', error);
                    resolve('');
                }
            });
        });
    }

    function getClientName(leadId) {
        console.log('🔍 DEBUG: Поиск имени клиента для lead:', leadId);

        // Вариант A: Ищем в блоке с заголовком "имя клиента"
        const $nameBlock = $('.lead-name_ihey').has('.zayv_stold_name:contains("имя клиента")');
        const $nameField = $nameBlock.find(`[data-lead-id="${leadId}"][data-field-type="name"]`);

        if ($nameField.length) {
            const name = $nameField.find('.field-text').text().trim();
            console.log('Найденное имя:', name);

            if (name && name !== 'Не указано' && name !== 'Не указан') {
                return name;
            }
        }

        // Вариант B: Ищем в глобальных данных
        if (window.leadsData?.[leadId]?.name) {
            const name = window.leadsData[leadId].name.trim();
            if (name && name !== 'Не указано') {
                return name;
            }
        }

        return '';
        // 3. Проверяем глобальные данные (если есть)
        if (window.leadsData && window.leadsData[leadId]) {
            const name = window.leadsData[leadId].name;
            if (name && name !== 'Не указано') {
                console.log('✅ DEBUG: Имя найдено в глобальных данных:', name);
                return name;
            }
        }

        // 4. Дополнительный поиск по классу lead-name_ihey с другим подходом
        const $leadNameDiv = $(`.lead-name_ihey [data-lead-id="${leadId}"]`).closest('.lead-name_ihey');
        if ($leadNameDiv.length) {
            // Ищем любой текст, похожий на имя
            const text = $leadNameDiv.text().trim();
            const words = text.split('\n').map(w => w.trim()).filter(w => w);

            for (const word of words) {
                if (word && word !== 'Не указано' && /^[А-ЯЁ][а-яё]+\s+[А-ЯЁ][а-яё]+$/.test(word)) {
                    console.log('✅ DEBUG: Имя найдено в тексте:', word);
                    return word;
                }
            }
        }

        console.log('⚠️ DEBUG: Имя клиента не найдено');
        return '';
    }


    function getDialogName(leadId, dialogId) {
        console.log('🔍 DEBUG: Поиск названия диалога:', { leadId, dialogId });

        if (dialogsState[leadId] && dialogsState[leadId].dialogs) {
            const dialog = dialogsState[leadId].dialogs.find(d => parseInt(d.id) === parseInt(dialogId));
            if (dialog && dialog.name) {
                console.log('✅ DEBUG: Название найдено в состоянии:', dialog.name);
                return dialog.name;
            }
        }

        const $dialogItem = $(`.dialog-item[data-dialog-id="${dialogId}"]`);
        if ($dialogItem.length) {
            const $dialogName = $dialogItem.find('strong');
            if ($dialogName.length) {
                const name = $dialogName.text().trim();
                console.log('✅ DEBUG: Название найдено в DOM:', name);
                return name;
            }
        }

        console.log('⚠️ DEBUG: Название не найдено, используем ID');
        return 'Диалог #' + dialogId;
    }// ==================== ВСПОМОГАТЕЛЬНЫЕ ФУНКЦИИ ДЛЯ ДИАЛОГОВ ====================




    function getDialogEmail(leadId, dialogId) {
        // Проверяем глобальное состояние
        if (dialogsState[leadId] && dialogsState[leadId].dialogs) {
            const dialog = dialogsState[leadId].dialogs.find(d => parseInt(d.id) === parseInt(dialogId));
            if (dialog && dialog.email && dialog.email !== 'Email не указан') {
                return dialog.email;
            }
        }

        // Проверяем DOM
        const $dialogContainer = $(`.dialog-email-container[data-lead-id="${leadId}"][data-dialog-id="${dialogId}"]`);
        if ($dialogContainer.length) {
            const emailText = $dialogContainer.find('.dialog-email-text').text();
            if (emailText && emailText !== 'Email не указан') {
                return emailText;
            }
        }

        return null;
    }




    function createFileWindow(leadId, dialogId) {
        var windowHtml = `
        <div class="file-creation-window" id="file-window-${leadId}-${dialogId}" style="display: none;">
            <div style="padding: 15px; border: 1px solid #ccc; background: white;">
                <h4>Создание файла (lead: ${leadId}, dialog: ${dialogId})</h4>
                <textarea class="file-content-editor" style="width: 100%; height: 200px;"></textarea>
                <div class="file-status"></div>
                <button class="button generate-pdf-btn-dialog" 
                    data-lead-id="${leadId}" data-dialog-id="${dialogId}">
                    Создать PDF
                </button>
            </div>
        </div>
    `;

        $('body').append(windowHtml);
    }


    // Закрытие окна файла
    $(document).on('click', '.close-file-window-dialog', function (e) {
        e.preventDefault();
        e.stopPropagation();

        var $fileWindow = $(this).closest('.file-creation-window');
        $fileWindow.slideUp(300);
    });


    // ==================== ИНИЦИАЛИЗАЦИЯ ДАННЫХ ЗАЯВОК ====================

    let leadsData = {};

    // Функция для загрузки данных заявки
    function loadLeadData(leadId) {
        if (!leadsData[leadId]) {
            // Загружаем данные заявки через AJAX если нужно
            $.ajax({
                url: crm_ajax.ajaxurl,
                type: 'POST',
                data: {
                    action: 'get_lead_data',
                    lead_id: leadId,
                    nonce: crm_ajax.nonce
                },
                success: function (response) {
                    if (response.success) {
                        leadsData[leadId] = response.data;
                        console.log('✅ DEBUG: Данные заявки загружены:', response.data);
                    }
                }
            });
        }
    }



    function loadDialogsForLead(leadId) {
        console.log('=== loadDialogsForLead вызван для lead:', leadId);
        console.log('=== Nonce значение:', crm_ajax.nonce);
        console.log('=== AJAX URL:', crm_ajax.ajaxurl);
        loadLeadData(leadId);
        initDialogsState(leadId);

        $.ajax({
            url: crm_ajax.ajaxurl,
            type: 'POST',
            data: {
                action: 'get_dialogs',
                lead_id: leadId,
                nonce: crm_ajax.nonce
            },
            success: function (response) {
                console.log('AJAX успех:', response);
                if (response.success) {
                    // ⭐ ВАЖНОЕ ИСПРАВЛЕНИЕ: Всегда обновляем состояние диалогов
                    dialogsState[leadId].dialogs = response.data.sort((a, b) => {
                        return new Date(b.created_at) - new Date(a.created_at);
                    });

                    console.log('📅 DEBUG: Диалоги после сортировки:', dialogsState[leadId].dialogs.map(d => ({
                        id: d.id,
                        name: d.name,
                        created_at: d.created_at
                    })));

                    // ⭐ КРИТИЧЕСКОЕ ИСПРАВЛЕНИЕ: Правильно переключаем сценарии
                    if (response.data.length > 0) {
                        $('#scenario1-' + leadId).hide();
                        $('#scenario2-' + leadId).show();
                        console.log('✅ DEBUG: Переключили на сценарий 2 (есть диалоги)');
                    } else {
                        $('#scenario1-' + leadId).show();
                        $('#scenario2-' + leadId).hide();
                        console.log('✅ DEBUG: Переключили на сценарий 1 (нет диалогов)');
                    }

                    // Всегда рендерим диалоги
                    renderDialogs(leadId);
                }
            },
            error: function (xhr, status, error) {
                console.error('Ошибка загрузки диалогов:', error);
                showNotification('Ошибка загрузки диалогов', 'error');
            }
        });
    }
    $(document).on('click', '.confirm-create-dialog', function (e) {
        e.preventDefault();
        var leadId = $(this).data('lead-id');

        var dialogName = '';
        if ($('#scenario1-' + leadId).is(':visible')) {
            dialogName = $('#scenario1-' + leadId + ' .new-dialog-name').val().trim();
        } else {
            dialogName = $('#scenario2-' + leadId + ' .new-dialog-name').val().trim();
        }

        if (!dialogName) {
            showNotification('Введите название диалога', 'error');
            return;
        }

        var $button = $(this);
        var originalText = $button.text();
        $button.text('Создание...').prop('disabled', true);

        $.ajax({
            url: crm_ajax.ajaxurl,
            type: 'POST',
            data: {
                action: 'create_dialog',
                lead_id: leadId,
                dialog_name: dialogName,
                // nonce: crm_ajax.nonce
            },
            success: function (response) {
                console.log('🔍 DEBUG: Полный ответ создания диалога:', response);

                if (response.success) {
                    $('#createDialogForm-' + leadId).hide();
                    $('#createDialogForm2-' + leadId).hide();
                    $('.new-dialog-name').val('');

                    console.log('📋 DEBUG: Новый диалог из ответа:', response.data.dialog);

                    // ⭐ ИСПРАВЛЕНИЕ: Добавляем диалог в состояние
                    if (response.data.dialog) {
                        dialogsState[leadId].dialogs.unshift(response.data.dialog);
                    }

                    console.log('📋 DEBUG: Диалоги после добавления:', dialogsState[leadId].dialogs);

                    // ⭐ КРИТИЧЕСКОЕ ИСПРАВЛЕНИЕ: Принудительно переключаем на сценарий 2
                    $('#scenario1-' + leadId).hide();
                    $('#scenario2-' + leadId).show();

                    console.log('✅ DEBUG: Принудительно переключили на сценарий 2 после создания первого диалога');

                    // Сразу рендерим без перезагрузки
                    renderDialogs(leadId);

                    showNotification('Диалог успешно создан', 'success');
                } else {
                    console.error('Ошибка создания диалога:', response.data);
                    showNotification('Ошибка: ' + response.data, 'error');
                }
            },
            error: function (xhr, status, error) {
                console.error('Ошибка сети:', error);
                showNotification('Ошибка сети при создании диалога', 'error');
            },
            complete: function () {
                $button.text(originalText).prop('disabled', false);
            }
        });
    });

    // ==================== СОЗДАНИЕ НОВОГО ДИАЛОГА (НОВЫЙ ИНТЕРФЕЙС) ====================
    $(document).on('click', '.create-dialog-btn', function (e) {
        e.preventDefault();
        var leadId = $(this).data('lead-id');
        var $scenario1 = $('#scenario1-' + leadId);

        if ($scenario1.is(':visible')) {
            $('#createDialogForm-' + leadId).show();
        } else {
            $('#createDialogForm2-' + leadId).show();
        }
    });

    $(document).on('click', '.cancel-create-dialog', function (e) {
        e.preventDefault();
        var leadId = $(this).data('lead-id');
        $('#createDialogForm-' + leadId).hide();
        $('#createDialogForm2-' + leadId).hide();
        $('#scenario1-' + leadId + ' .new-dialog-name').val('');
        $('#scenario2-' + leadId + ' .new-dialog-name').val('');
    });




    $(document).on('click', '.create-new-dialog', function (e) {
        e.preventDefault();
        var leadId = $(this).data('lead-id');
        var $panel = $('#dialog-panel-' + leadId);
        var $dialogNameInput = $panel.find('.new-dialog-name');

        // Показываем поле для ввода имени диалога
        if ($dialogNameInput.is(':hidden')) {
            $dialogNameInput.show();
            $dialogNameInput.focus();
            return;
        }

        var dialogName = $dialogNameInput.val().trim();

        if (dialogName === '') {
            showNotification('Пожалуйста, введите название диалога', 'error');
            return;
        }

        // Показываем индикатор загрузки
        var $button = $(this);
        var originalText = $button.text();
        $button.text('Создание...').prop('disabled', true);

        // Отправляем запрос на создание диалога
        $.ajax({
            url: crm_ajax.ajaxurl,
            type: 'POST',
            data: {
                action: 'create_dialog',
                lead_id: leadId,
                dialog_name: dialogName,
                nonce: crm_ajax.nonce
            },
            success: function (response) {
                if (response.success) {
                    console.log('✅ Диалог создан успешно! ID: ' + response.data.dialog_id);

                    // Обновляем список диалогов
                    loadDialogsForLead(leadId);

                    // Скрываем поле ввода и очищаем его
                    $dialogNameInput.hide().val('');

                    // Показываем сообщение об успехе
                    showNotification('Диалог успешно создан', 'success');

                    // Автоматически выбираем созданный диалог
                    setTimeout(function () {
                        $panel.find('.dialog-selector').val(response.data.dialog_id);
                    }, 500);

                } else {
                    console.log('❌ Диалог не создан: ' + response.data);
                    showNotification('Ошибка: ' + response.data, 'error');
                }
            },
            error: function (xhr, status, error) {
                console.log('❌ Ошибка при создании диалога: ' + error);
                showNotification('Ошибка сети при создании диалога', 'error');
            },
            complete: function () {
                $button.text(originalText).prop('disabled', false);
            }
        });
    });

    // ==================== ГЕНЕРАЦИЯ ФАЙЛОВ ====================


    function getEditorContent(leadId) {
        console.log('🔍 DEBUG: Поиск редактора для lead:', leadId);

        // Ищем редактор в активном диалоге
        const $activeDialog = $('.dialog-item.active');
        const editor = $activeDialog.find('.file-content-editor')[0];

        if (editor) {
            console.log('✅ DEBUG: Редактор найден, содержимое:', editor.innerHTML.substring(0, 50) + '...');
            return editor.innerHTML;
        } else {
            console.log('❌ DEBUG: Редактор не найден в активном диалоге');
            // Попробуем найти другим способом
            const fileWindow = document.querySelector(`#file-window-${leadId}`);
            if (fileWindow) {
                const fallbackEditor = fileWindow.querySelector('.file-content-editor');
                if (fallbackEditor) {
                    console.log('✅ DEBUG: Редактор найден через fallback');
                    return fallbackEditor.innerHTML;
                }
            }
        }

        console.log('❌ DEBUG: Редактор не найден вообще');
        return '';
    }

    // Функция для получения чистого текста из редактора
    function getEditorText(leadId) {
        const fileWindow = document.querySelector(`#file-window-${leadId}`);
        if (fileWindow) {
            const editor = fileWindow.querySelector('.file-content-editor');
            return editor ? editor.innerText || editor.textContent : '';
        }
        return '';
    }

    $(document).on('click', '.generate-pdf-btn-dialog', function (e) {
        e.preventDefault();
        e.stopPropagation();

        var leadId = $(this).data('lead-id');
        var dialogId = $(this).data('dialog-id');
        console.log('🔍 DEBUG: Генерация PDF для:', leadId, dialogId);

        // Находим окно файла
        var $fileWindow = $(this).closest('.file-creation-window');
        if ($fileWindow.length === 0) {
            $fileWindow = $('#file-window-' + leadId + '-' + dialogId);
        }

        var fileContent = $fileWindow.find('.file-content-editor').html();
        var $status = $fileWindow.find('.file-status');

        // 🔥 СОХРАНЯЕМ currentFile ПЕРЕД AJAX ЗАПРОСОМ
        var $redactorInfo = $fileWindow.find('.redactor_file');
        var currentFile = $redactorInfo.text().trim();
        var currentFileName = currentFile.replace('редактируется ', '');

        console.log('🔍 DEBUG: Текущий файл:', currentFile);
        console.log('🔍 DEBUG: Имя файла для поиска:', currentFileName);


        console.log('📝 DEBUG: Содержимое редактора:', {
            length: fileContent ? fileContent.length : 0,
            content: fileContent ? fileContent.substring(0, 100) + '...' : 'empty',
            editorFound: $fileWindow.find('.file-content-editor').length
        });

        if (!fileContent || fileContent.trim() === '' || fileContent === '<br>') {
            $status.html('<div class="notice notice-error">Введите текст для документа PDF</div>');
            return;
        }

        // 🔥 НОВАЯ ЛОГИКА: БЕРЕМ ИМЯ ИЗ РЕДАКТИРУЕМОГО ФАЙЛА
        var $redactorInfo = $fileWindow.find('.redactor_file');
        var currentFile = $redactorInfo.text().trim();
        var pdfFileName = '';

        if (currentFile !== 'редактируется новый') {
            // 🔥 ЕСЛИ РЕДАКТИРУЕТСЯ СУЩЕСТВУЮЩИЙ ФАЙЛ - КОПИРУЕМ ИМЯ
            pdfFileName = currentFile.replace('редактируется ', '') + '.pdf';
            console.log('📄 DEBUG: Используем имя редактируемого файла:', pdfFileName);
        } else {
            // 🔥 СТАРАЯ ЛОГИКА: ЕСЛИ РЕДАКТИРУЕТСЯ НОВЫЙ - ОСТАВЛЯЕМ АВТО-ИМЯ
            console.log('🆕 DEBUG: Используем авто-имя для нового файла:', pdfFileName);
        }

        var $button = $(this);
        var originalText = $button.text();
        $button.text('Создание PDF...').prop('disabled', true);
        $status.html('<div class="notice notice-info">Создание PDF документа...</div>');

        // 🔥 ДИАГНОСТИКА: Проверяем параметры AJAX
        console.log('🔧 DEBUG: Параметры AJAX:', {
            ajaxurl: crm_ajax.ajaxurl,
            nonce: crm_ajax.nonce,
            lead_id: leadId,
            dialog_id: dialogId,
            content_length: fileContent.length,
            pdf_file_name: pdfFileName // 🔥 ПЕРЕДАЕМ ИМЯ ФАЙЛА
        });

        $.ajax({
            url: crm_ajax.ajaxurl,
            type: 'POST',
            data: {
                action: 'generate_pdf_file',
                lead_id: leadId,
                dialog_id: dialogId,
                file_content: fileContent,
                custom_file_name: pdfFileName, // 🔥 ПЕРЕДАЕМ ИМЯ ФАЙЛА
                nonce: crm_ajax.nonce
            },
            success: function (response) {
                console.log('✅ DEBUG: Успешный ответ:', response);
                //   console.log(fileName);

                if (response.success) {
                    console.log('🎨 currentFileName:', currentFileName);
                    $status.html('<div class="notice notice-success">' + response.data.message + '</div>');
                    addFileToMessage(leadId, response.data.file_url, response.data.file_name, 'pdf');


                } else {
                    console.log('❌ DEBUG: Ошибка в ответе:', response.data);
                    $status.html('<div class="notice notice-error">Ошибка: ' + response.data + '</div>');
                    showNotification('Ошибка создания PDF: ' + response.data, 'error');
                }
            },
            error: function (xhr, status, error) {
                console.log('❌ DEBUG: Ошибка AJAX:', {
                    status: status,
                    error: error,
                    readyState: xhr.readyState,
                    statusCode: xhr.status,
                    responseText: xhr.responseText
                });

                var errorMsg = 'Ошибка сети: ' + error;
                if (xhr.status === 500) {
                    errorMsg = 'Ошибка сервера (500). Проверьте логи PHP.';
                } else if (xhr.status === 403) {
                    errorMsg = 'Ошибка доступа (403). Проверьте nonce.';
                }

                $status.html('<div class="notice notice-error">' + errorMsg + '</div>');
                showNotification(errorMsg, 'error');
            },
            complete: function () {
                $button.text(originalText).prop('disabled', false);
            }
        });
    });


    $(document).on('click', '.generate-jpg-a4-btn-dialog', function (e) {
        e.preventDefault();
        e.stopPropagation();

        var leadId = $(this).data('lead-id');
        var dialogId = $(this).data('dialog-id');
        console.log('Генерация JPG для:', leadId, dialogId);

        // Получаем message_id из активного сообщения (если есть)
        var messageId = 0;
        var $activeDialog = $('.dialog-item.active');
        var $lastMessage = $activeDialog.find('.messages-list .message-item').last();
        if ($lastMessage.length) {
            messageId = $lastMessage.data('message-id') || 0;
        }

        // Находим окно файла
        var $fileWindow = $(this).closest('.file-creation-window');
        if ($fileWindow.length === 0) {
            $fileWindow = $('#file-window-' + leadId + '-' + dialogId);
        }

        var fileContent = $fileWindow.find('.file-content-editor').html();
        var $status = $fileWindow.find('.file-status');

        if (!fileContent || fileContent.trim() === '' || fileContent === '<br>') {
            $status.html('<div class="notice notice-error">Введите текст для документа JPG</div>');
            return;
        }

        // 🔥 ЛОГИКА С ИМЕНЕМ ФАЙЛА (КАК У PDF)
        var $redactorInfo = $fileWindow.find('.redactor_file');
        var currentFile = $redactorInfo.text().trim();

        var pdfData = {
            action: 'generate_pdf_file',
            lead_id: leadId,
            dialog_id: dialogId,
            file_content: fileContent,
            message_id: messageId,
        };

        // 🔥 ЕСЛИ РЕДАКТИРУЕТСЯ ФАЙЛ - ДОБАВЛЯЕМ ЕГО ИМЯ
        if (currentFile !== 'редактируется новый') {
            var fileName = currentFile.replace('редактируется ', '') + '.pdf';
            pdfData.custom_file_name = fileName;
            console.log('📄 DEBUG: Используем имя редактируемого файла для PDF:', fileName);
        }

        var $button = $(this);
        var originalText = $button.text();
        $button.text('Создание JPG...').prop('disabled', true);
        $status.html('<div class="notice notice-info">Создание JPG документа...</div>');

        function continueWithPdfGeneration() {
            $.ajax({
                url: crm_ajax.ajaxurl,
                type: 'POST',
                data: pdfData,
                success: function (response) {
                    if (response.success) {
                        console.log('✅ PDF создан, конвертируем в JPG...');

                        $.ajax({
                            url: crm_ajax.ajaxurl,
                            type: 'POST',
                            data: {
                                action: 'generate_jpg_file',
                                pdf_url: response.data.file_url,
                                pdf_filename: response.data.file_name,
                                lead_id: leadId,
                                dialog_id: dialogId,
                                message_id: messageId,
                                nonce: crm_ajax.nonce
                            },
                            success: function (jpgResponse) {
                                if (jpgResponse.success) {
                                    console.log('✅ ZIP архив создан с ' + jpgResponse.data.total_pages + ' страницами');



                                    // Добавляем файл к сообщению
                                    addFileToMessage(leadId, jpgResponse.data.file_url, jpgResponse.data.file_name, 'zip');
                                    $status.html('<div class="notice notice-success">' + jpgResponse.data.message + '</div>');

                                } else {
                                    $status.html('<div class="notice notice-error">Ошибка: ' + jpgResponse.data + '</div>');
                                }
                            },
                            error: function (xhr, status, error) {
                                $status.html('<div class="notice notice-error">Ошибка сети: ' + error + '</div>');
                            },
                            complete: function () {
                                $button.text(originalText).prop('disabled', false);
                            }
                        });

                    } else {
                        $status.html('<div class="notice notice-error">Ошибка PDF: ' + response.data + '</div>');
                        $button.text(originalText).prop('disabled', false);
                    }
                },
                error: function (xhr, status, error) {
                    $status.html('<div class="notice notice-error">Ошибка сети: ' + error + '</div>');
                    $button.text(originalText).prop('disabled', false);
                }
            });
        }

        continueWithPdfGeneration();
    });



    // ==================== ОБРАБОТКА ВЫБОРА ДИАЛОГА ====================
    $(document).on('change', '.dialog-selector', function () {
        var dialogId = $(this).val();
        var leadId = $(this).data('lead-id');
        var $panel = $('#dialog-panel-' + leadId);

        if (dialogId) {
            console.log('Выбран диалог ID: ' + dialogId + ' для заявки: ' + leadId);
            // Загружаем историю сообщений при выборе диалога
            loadDialogMessages(dialogId, $panel);
        } else {
            $panel.find('.messages-history').hide();
        }
    });

    // ==================== ОБНОВЛЕНИЕ СТАТУСА ЗАЯВКИ ====================
    $(document).on('change', '.status-select', function () {
        var leadId = $(this).data('lead-id');
        var newStatus = $(this).val();

        $.ajax({
            url: crm_ajax.ajaxurl,
            type: 'POST',
            data: {
                action: 'update_lead_status',
                lead_id: leadId,
                status: newStatus,

            },
            success: function (response) {
                if (response.success) {
                    console.log('✅ Статус обновлен');
                    showNotification('Статус заявки обновлен', 'success');

                    // Обновляем статистику
                    if (response.data && response.data.stats) {
                        updateStatsDisplay(response.data.stats);
                    } else {
                        refreshStats();
                    }

                    // Меняем класс у основной заявки
                    var leadContainer = $('.lead-row[data-lead-id="' + leadId + '"]');
                    if (leadContainer.length > 0) {
                        // Удаляем старые классы статусов
                        leadContainer.removeClass('status-xolod status-sozvon status-otpr status-tepl status-gorak');

                        // Добавляем новый класс статуса
                        leadContainer.addClass('status-' + newStatus);

                        console.log('✅ Класс заявки обновлен: status-' + newStatus);

                        // ПРОВЕРЯЕМ АКТИВНЫЙ ФИЛЬТР И СКРЫВАЕМ ЗАЯВКУ ЕСЛИ НЕ СООТВЕТСТВУЕТ
                        checkAndHideIfNotMatchesFilter(leadId, newStatus);
                    }
                } else {
                    console.log('❌ Ошибка обновления статуса');
                    showNotification('Ошибка обновления статуса', 'error');
                }
            },
            error: function (xhr, status, error) {
                console.log('❌ Ошибка сети при обновлении статуса: ' + error);
                showNotification('Ошибка сети при обновлении статуса', 'error');
            }
        });
    });

    // Функция проверки соответствия фильтру и скрытия заявки
    function checkAndHideIfNotMatchesFilter(leadId, newStatus) {
        // Получаем активный фильтр
        var activeFilter = $('.stat-card.active-filter').data('filter');

        // Если активен фильтр "все" или фильтр соответствует статусу - ничего не делаем
        if (activeFilter === 'all' || activeFilter === newStatus) {
            console.log('✅ Заявка соответствует активному фильтру');
            return;
        }

        // Если фильтр активен и статус не соответствует - скрываем заявку
        var leadWrapper = $('.lead_wap_content').has('.lead-row[data-lead-id="' + leadId + '"]');
        if (leadWrapper.length > 0) {
            leadWrapper.hide();
            console.log('🚫 Заявка скрыта - не соответствует фильтру: ' + activeFilter);

            // Перераспределяем оставшиеся видимые заявки
            redistributeLeadWrappers();

            // Показываем уведомление
            showNotification('Заявка скрыта - не соответствует активному фильтру', 'info', 3000);
        }
    }

    // Функция для обновления отображения статистики
    function updateStatsDisplay(stats) {
        console.log('Обновление статистики:', stats);
        // Обновляем все счетчики
        $('.stat-card.status-all .stat-number').text(stats.total || 0);
        $('.stat-card.status-xolod .stat-number').text(stats.xolod || 0);
        $('.stat-card.status-sozvon .stat-number').text(stats.sozvon || 0);
        $('.stat-card.status-otpr .stat-number').text(stats.otpr || 0);
        $('.stat-card.status-tepl .stat-number').text(stats.tepl || 0);
        $('.stat-card.status-gorak .stat-number').text(stats.gorak || 0);

        // Сохраняем текущий активный фильтр
        var currentFilter = $('.stat-card.active-filter').data('filter');

        // Если есть активный фильтр, обновляем отображение
        if (currentFilter && currentFilter !== 'all') {
            filterLeadsByStatus(currentFilter);
        }
    }

    // Функция для принудительного обновления статистики
    function refreshStats() {
        $.ajax({
            url: crm_ajax.ajaxurl,
            type: 'POST',
            data: {
                action: 'get_crm_stats',
                nonce: crm_ajax.nonce
            },
            success: function (response) {
                if (response.success) {
                    updateStatsDisplay(response.data);
                } else {
                    console.log('❌ Ошибка загрузки статистики');
                }
            },
            error: function (xhr, status, error) {
                console.log('❌ Ошибка сети при загрузке статистики: ' + error);
            }
        });
    }

    // Функция для фильтрации заявок по статусу
    function filterLeadsByStatus(status) {
        var allLeadWrappers = $('.lead_wap_content');

        if (status === 'all') {
            // Показываем все заявки и их контейнеры
            allLeadWrappers.show();
            console.log('✅ Показаны все заявки');
        } else {
            // Скрываем все контейнеры
            allLeadWrappers.hide();

            // Показываем только контейнеры с заявками нужного статуса
            var filteredWrappers = $('.lead_wap_content .lead-row.status-' + status).parent();
            filteredWrappers.show();

            console.log('✅ Показаны заявки со статусом: ' + status + ' (' + filteredWrappers.length + ' шт.)');

            // Перераспределяем контейнеры без пустых мест
            redistributeLeadWrappers();
        }

        // Обновляем активный фильтр
        $('.stat-card').removeClass('active-filter');
        $('.stat-card[data-filter="' + status + '"]').addClass('active-filter');

        // Сохраняем активный фильтр в data-атрибут для возможности проверки
        $('.crm-table').data('active-filter', status);
    }

    // Функция для получения текущего активного фильтра
    function getActiveFilter() {
        return $('.stat-card.active-filter').data('filter') || 'all';
    }

    // Функция для перераспределения контейнеров заявок
    function redistributeLeadWrappers() {
        var container = $('.crm-table');
        var visibleWrappers = $('.lead_wap_content:visible');

        // Перемещаем все видимые контейнеры в начало
        visibleWrappers.each(function (index) {
            container.append($(this));
        });

        console.log('🔄 Контейнеры перераспределены: ' + visibleWrappers.length + ' шт.');
    }

    // Функция для перераспределения заявок без пустых мест
    function redistributeLeads() {
        var container = $('.crm-table');
        var visibleLeads = $('.lead-row:visible');

        // Временно отключаем анимацию для плавного перемещения
        container.css('opacity', '0.7');

        // Перемещаем все видимые заявки в начало контейнера
        visibleLeads.each(function (index) {
            container.append($(this));
        });

        // Включаем обратно прозрачность
        setTimeout(function () {
            container.css('opacity', '1');
        }, 100);

        console.log('🔄 Заявки перераспределены: ' + visibleLeads.length + ' шт.');
    }

    // Обработчик клика по фильтрам
    $(document).on('click', '.stat-card', function () {
        var filterStatus = $(this).data('filter');
        filterLeadsByStatus(filterStatus);
    });

    // Обработчик клика по фильтрам
    $(document).on('click', '.stat-card', function () {
        var filterStatus = $(this).data('filter');
        filterLeadsByStatus(filterStatus);
    });

    // Инициализация при загрузке страницы - показываем все заявки
    $(document).ready(function () {
        $('.stat-card[data-filter="all"]').addClass('active-filter');
    });

    // Функция для обновления итогового статуса
    function updateDocumentStatus(button) {
        console.log('=== ПРОВЕРКА СТАТУСА ДОКУМЕНТОВ ДЛЯ КОНКРЕТНОЙ ЗАЯВКИ ===');

        // Находим родительский блок заявки
        const leadBlock = button.closest('.lead-name_ihey');
        const statusElements = leadBlock.querySelectorAll('.doc_spisok_text');
        const totalStatusElement = leadBlock.querySelector('.doc_spisok_itog');

        console.log('Всего полей в этой заявке:', statusElements.length);

        let allCompleted = true;
        let emptyFieldsCount = 0;

        // Проверяем каждое поле только в этой заявке
        statusElements.forEach((element, index) => {
            const isFilled = element.classList.contains('filled-data');
            const isEmpty = element.classList.contains('empty-data');
            const fieldName = element.closest('.doc_spisok_item').querySelector('.doc_spisok_name').textContent.trim();

            console.log(`Поле ${index + 1}: "${fieldName}"`, {
                filled: isFilled,
                empty: isEmpty,
                status: isFilled ? '✅ ЗАПОЛНЕНО' : '❌ НЕ ЗАПОЛНЕНО'
            });

            if (isEmpty) {
                allCompleted = false;
                emptyFieldsCount++;
            }
        });

        console.log('--- ИТОГ ДЛЯ ЭТОЙ ЗАЯВКИ ---');
        console.log('Незаполненных полей:', emptyFieldsCount);
        console.log('Все поля заполнены:', allCompleted);

        // Обновляем итоговый статус
        if (allCompleted) {
            totalStatusElement.innerHTML = '<img draggable="false" role="img" class="emoji" alt="✅" src="https://s.w.org/images/core/emoji/16.0.1/svg/2705.svg">';
            console.log('✅ СТАТУС: ВСЕ ДОКУМЕНТЫ ЗАПОЛНЕНЫ');
        } else {
            totalStatusElement.innerHTML = '<img draggable="false" role="img" class="emoji" alt="❌" src="https://s.w.org/images/core/emoji/16.0.1/svg/274c.svg">';
            console.log('❌ СТАТУС: ЕСТЬ НЕЗАПОЛНЕННЫЕ ПОЛЯ');
        }

        console.log('==============================');
    }

    // Назначаем обработчики на все кнопки "обновить"
    document.querySelectorAll('.obnova_doc_stat').forEach(button => {
        button.addEventListener('click', function (e) {
            e.preventDefault();
            console.log('🔄 КНОПКА "ОБНОВИТЬ" НАЖАТА ДЛЯ ЗАЯВКИ');
            updateDocumentStatus(this);
        });
    });




    // ==================== УПРАВЛЕНИЕ ОКНОМ СОЗДАНИЯ ФАЙЛА ====================

    // Открытие окна создания файла
    $(document).on('click', '.create-file-btn', function (e) {
        e.preventDefault();
        var leadId = $(this).data('lead-id');
        var $fileWindow = $('#file-window-' + leadId);

        // Закрываем все другие открытые окна создания файлов
        $('.file-creation-window:visible').not($fileWindow).slideUp(300);

        // Переключаем текущее окно
        if ($fileWindow.is(':visible')) {
            $fileWindow.slideUp(300);
        } else {
            // Очищаем поле файла при открытии
            $fileWindow.find('.file-content').val('');
            $fileWindow.find('.file-status').empty();
            $fileWindow.slideDown(300);
        }
    });

    // Закрытие окна создания файла
    $(document).on('click', '.close-file-window', function (e) {
        e.preventDefault();
        var leadId = $(this).data('lead-id');
        $('#file-window-' + leadId).slideUp(300);
    });

    // ==================== УПРАВЛЕНИЕ ПРИКРЕПЛЕННЫМИ ФАЙЛАМИ ====================


    // Функция для определения иконки файла
    function getFileIcon(fileType) {
        if (!fileType) return '📄';

        var fileTypeLower = fileType.toLowerCase();

        if (['jpg', 'jpeg', 'png', 'gif', 'bmp', 'webp', 'svg'].includes(fileTypeLower)) {
            return '🖼️';
        } else if (fileTypeLower === 'pdf') {
            return '📄';
        } else if (['doc', 'docx'].includes(fileTypeLower)) {
            return '📝';
        } else if (['xls', 'xlsx'].includes(fileTypeLower)) {
            return '📊';
        } else if (['zip', 'rar', '7z', 'tar', 'gz'].includes(fileTypeLower)) {
            return '📦';
        } else if (['mp4', 'avi', 'mov', 'wmv', 'flv'].includes(fileTypeLower)) {
            return '🎬';
        } else if (['mp3', 'wav', 'ogg', 'flac'].includes(fileTypeLower)) {
            return '🎵';
        } else if (['txt', 'rtf'].includes(fileTypeLower)) {
            return '📃';
        } else {
            return '📄';
        }
    }
    // Обработчик для кнопки "Прикрепить файл"
    $(document).on('click', '.send_prik_firld', function () {
        var leadId = $(this).data('lead-id');
        var dialogId = $(this).data('dialog-id');

        console.log('🖱️ DEBUG: Нажата кнопка Прикрепить файл', leadId, dialogId);

        // Создаем невидимый input для выбора файла
        var $fileInput = $('<input type="file" style="display: none;">');
        $('body').append($fileInput);

        // Обработчик выбора файлов
        $fileInput.on('change', function (e) {
            var files = e.target.files;
            console.log('📁 DEBUG: Выбрано файлов:', files.length);

            if (files.length > 0) {
                var file = files[0];
                uploadFileToServer(file, leadId, dialogId);
            }

            // Удаляем input после выбора
            $fileInput.remove();
        });

        // Открываем проводник
        $fileInput.click();
    });


    // Функция загрузки файла на сервер
    function uploadFileToServer(file, leadId, dialogId) {
        console.log('📤 DEBUG: Загрузка файла:', {
            name: file.name,
            size: file.size,
            type: file.type,
            leadId: leadId,
            dialogId: dialogId
        });

        var $activeDialog = $('.dialog-item.active');
        var $status = $activeDialog.find('.file-upload-status');

        if ($status.length === 0) {
            $status = $('<div class="file-upload-status" style="margin: 10px 0;"></div>');
            $activeDialog.find('.attachments-container').before($status);
        }

        $status.html('<div class="notice notice-info">Загрузка файла "' + file.name + '"...</div>');

        var formData = new FormData();
        formData.append('action', 'upload_crm_file');
        formData.append('crm_file', file);
        formData.append('lead_id', leadId);
        formData.append('dialog_id', dialogId); // 🔥 ПЕРЕДАЕМ DIALOG_ID

        $.ajax({
            url: crm_ajax.ajaxurl,
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            success: function (response) {
                console.log('✅ DEBUG: Файл загружен успешно:', response);

                if (response.success) {
                    $status.html('<div class="notice notice-success">' + response.data.message + '</div>');

                    // Добавляем файл к сообщению
                    addFileToMessage(
                        leadId,
                        response.data.file_url,
                        response.data.original_name,
                        response.data.file_type
                    );

                    showNotification('Файл "' + response.data.original_name + '" успешно загружен', 'success');

                    // Через 3 секунды скрываем статус
                    setTimeout(function () {
                        $status.html('');
                    }, 3000);

                } else {
                    $status.html('<div class="notice notice-error">Ошибка: ' + response.data + '</div>');
                    showNotification('Ошибка загрузки файла: ' + response.data, 'error');
                }
            },
            error: function (xhr, status, error) {
                console.log('❌ DEBUG: Ошибка загрузки файла:', error);
                $status.html('<div class="notice notice-error">Ошибка сети: ' + error + '</div>');
                showNotification('Ошибка сети при загрузке файла', 'error');
            }
        });
    }

    // Функция для обработки выбранного файла
    function processSelectedFile(file, leadId, dialogId) {
        console.log('📄 DEBUG: Обработка файла:', file.name, file.type, file.size);

        // Определяем тип файла из расширения
        var fileNameParts = file.name.split('.');
        var fileExtension = fileNameParts.length > 1 ? fileNameParts.pop().toLowerCase() : '';
        var fileName = file.name;

        // Создаем временный URL для файла
        var fileUrl = URL.createObjectURL(file);

        console.log('🔗 DEBUG: Создан временный URL для файла');

        // Добавляем файл в интерфейс
        addFileToMessage(leadId, fileUrl, fileName, fileExtension);
    }

    // Удаление прикрепленного файла
    $(document).on('click', '.remove-attachment', function (e) {
        e.preventDefault();
        e.stopPropagation();

        var $attachmentItem = $(this).closest('.attachment-item');
        var $attachmentsList = $(this).closest('.attachments-list');
        var $attachmentsContainer = $(this).closest('.attachments-container');

        $attachmentItem.remove();


        console.log('🗑️ DEBUG: Файл удален, осталось файлов:', $attachmentsList.children().length);
        showNotification('Файл скрыт', 'info');
    });

    // Функция для принудительной очистки всех файлов в диалоге
    function clearAllAttachments(leadId, dialogId) {
        var $activeDialog = $('.dialog-item.active');
        var $attachmentsContainers = $activeDialog.find('.attachments-container');

        $attachmentsContainers.each(function () {
            var $container = $(this);
            var $attachmentsList = $container.find('.attachments-list');
            $attachmentsList.empty();
            $container.hide();
        });

        console.log('🧹 DEBUG: Все файлы очищены в диалоге', dialogId);
    }

    // ==================== ВСПОМОГАТЕЛЬНЫЕ ФУНКЦИИ ====================

    // Функция для показа уведомлений
    function showNotification(message, type) {
        // Создаем элемент уведомления
        var $notification = $('<div class="crm-notification notice notice-' + type + '">' + message + '</div>');

        // Добавляем в тело документа
        $('body').append($notification);

        // Позиционируем
        $notification.css({
            'position': 'fixed',
            'top': '20px',
            'right': '20px',
            'z-index': '100000',
            'padding': '10px 15px',
            'border-radius': '4px',
            'box-shadow': '0 2px 10px rgba(0,0,0,0.1)'
        });

        // Автоматически скрываем через 5 секунд
        setTimeout(function () {
            $notification.fadeOut(300, function () {
                $(this).remove();
            });
        }, 3000);
    }

    // Функция для обработки UTF-8 кодировки
    function ensureUTF8(str) {
        try {
            if (typeof str !== 'string') return str;

            // Убираем возможные BOM маркеры
            str = str.replace(/^\uFEFF/, '');

            return str;

        } catch (error) {
            console.error('UTF-8 conversion error:', error);
            return str;
        }
    }

    // Функция для форматирования даты
    function formatDate(dateString) {
        var date = new Date(dateString);
        return date.toLocaleDateString('ru-RU') + ' ' + date.toLocaleTimeString('ru-RU');
    }

    // ==================== ИНИЦИАЛИЗАЦИЯ ====================
    console.log('CRM система инициализирована');

    // Показываем уведомление о загрузке
    showNotification('CRM система готова к работе', 'success');

});

// Функция для получения HTML содержимого редактора
function getEditorContent() {
    const editor = document.querySelector('.file-content-editor');
    if (editor) {
        return editor.innerHTML;
    }
    return '';
}

// Функция для установки содержимого редактора
function setEditorContent(html) {
    const editor = document.querySelector('.file-content-editor');
    if (editor) {
        editor.innerHTML = html;
    }
}

// Функция для получения чистого текста (для обратной совместимости)
function getEditorText() {
    const editor = document.querySelector('.file-content-editor');
    if (editor) {
        return editor.innerText || editor.textContent;
    }
    return '';
}



// Автосохранение в localStorage
function setupAutoSave() {
    const editor = document.querySelector('.file-content-editor');
    const storageKey = 'crm_editor_content';

    // Загружаем сохраненное содержимое
    const saved = localStorage.getItem(storageKey);
    if (saved && !editor.innerHTML.includes('document-header')) {
        editor.innerHTML = saved;
    }

    // Сохраняем при изменении
    editor.addEventListener('input', function () {
        localStorage.setItem(storageKey, editor.innerHTML);
    });
}

// Инициализация при загрузке
document.addEventListener('DOMContentLoaded', function () {
    setupAutoSave();

    // Фокус на контенте при клике
    const editor = document.querySelector('.file-content-editor');
    editor.addEventListener('click', function (e) {
        if (e.target.classList.contains('document-header')) {
            const contentDiv = editor.querySelector('.document-content');
            if (contentDiv) {
                const range = document.createRange();
                const sel = window.getSelection();
                range.setStart(contentDiv, 0);
                range.collapse(true);
                sel.removeAllRanges();
                sel.addRange(range);
            }
        }
    });
});


function debugDialogsState(leadId) {
    console.log('=== DEBUG DIALOGS STATE ===');
    console.log('Lead ID:', leadId);
    console.log('Dialogs State:', dialogsState[leadId]);
    console.log('Scenario 1 visible:', $('#scenario1-' + leadId).is(':visible'));
    console.log('Scenario 2 visible:', $('#scenario2-' + leadId).is(':visible'));
    console.log('Dialogs list element:', $('#dialogsList-' + leadId).length);
    console.log('==========================');
}


// единая кнопка изменений 
// ОБРАБОТЧИК ДЛЯ РЕДАКТИРОВАНИЯ
$(document).on('click', '.edit-field-btn', function (e) {
    e.preventDefault();
    e.stopPropagation();

    const $container = $(this).closest('.field-container');
    const $textElement = $container.find('.field-text');
    const currentValue = $textElement.text().trim();
    const fieldType = $container.data('field-type');

    console.log('🖊️ DEBUG: Редактирование поля', currentValue);

    // Сохраняем оригинальное значение
    $container.data('original-value', currentValue);

    // Заменяем текст на поле ввода
    $textElement.replaceWith(`
        <input type="text" class="field-input" value="${currentValue}" 
               style="padding: 2px 4px; border: 1px solid #ccc; min-width: 150px;" placeholder="введите данные">
        <button type="button" class="save-field-btn">✓</button>
        <button type="button" class="cancel-field-btn">✕</button>
    `);

    if (fieldType === 'phone') {
        applyPhoneMask($container.find('.field-input'));
    }

    function applyPhoneMask($input) {
        $input.on('input', function () {
            let value = $(this).val().replace(/\D/g, '');

            if (value.length > 1) {
                if (!value.startsWith('7') && !value.startsWith('8')) {
                    value = '7' + value;
                }

                let formatted = '+7 (' + value.substring(1, 4) + ') ' +
                    value.substring(4, 7) + '-' +
                    value.substring(7, 9) + '-' +
                    value.substring(9, 11);
                $(this).val(formatted);
            }
        });
    }

    // Скрываем карандаш
    $(this).hide();
});



// ОБРАБОТЧИК ДЛЯ СОХРАНЕНИЯ - ВЫЗЫВАЕТ updateAnyField


$(document).on('click', '.save-field-btn', function (e) {
    e.preventDefault();
    e.stopPropagation();

    const $container = $(this).closest('.field-container');
    const newValue = $container.find('.field-input').val().trim();


    console.log('💾 DEBUG: Сохранение поля');

    // ВЫЗЫВАЕМ УНИВЕРСАЛЬНУЮ ФУНКЦИЮ
    updateAnyField($container, newValue);
});



// ОБРАБОТЧИК ДЛЯ ОТМЕНЫ
$(document).on('click', '.cancel-field-btn', function (e) {
    e.preventDefault();
    e.stopPropagation();

    const $container = $(this).closest('.field-container');
    const originalValue = $container.data('original-value');
    const fieldType = $container.data('field-type');
    const table = $container.data('table');

    // Восстанавливаем оригинальный текст в зависимости от типа поля
    $container.find('.field-input, .save-field-btn, .cancel-field-btn').remove();

    // ⭐ ДОБАВЛЕНО: ВОССТАНОВЛЕНИЕ ССЫЛКИ ДЛЯ ТЕЛЕФОНА
    if (fieldType === 'phone') {
        $container.prepend(`
            <a class="field-text phone-link" href="tel:${originalValue}">
                ${originalValue}
            </a>
        `);
    } else {
        $container.prepend(`<span class="field-text">${originalValue}</span>`);
    }

    $container.find('.edit-field-btn').show();

    // ДОПОЛНИТЕЛЬНО ДЛЯ ПОЛЕЙ ДОКУМЕНТОВ
    if ($container.closest('.doc_spisok_item').length) {
        // ВОССТАНАВЛИВАЕМ ОРИГИНАЛЬНУЮ СТРУКТУРУ
        const $docText = $container.find('.doc_spisok_text');

        // УДАЛЯЕМ НОВЫЙ field-text (который создали через prepend)
        $container.find('.field-text').first().remove();

        // ⭐ ИСПРАВЛЕНО: ВОССТАНАВЛИВАЕМ ПРАВИЛЬНУЮ СТРУКТУРУ ДЛЯ ТЕЛЕФОНА
        if (fieldType === 'phone') {
            $docText.empty().append(`
                <a class="field-text phone-link" href="tel:${originalValue}">
                    ${originalValue}
                </a>
            `);
        } else {
            $docText.empty().append(`<span class="field-text">${originalValue}</span>`);
        }

        // ОБНОВЛЯЕМ СТИЛИ
        updateFieldStyles($container, originalValue);
    }
});

// ⭐ ФУНКЦИЯ ПРОВЕРКИ ВАЛИДНОСТИ ТЕЛЕФОНА
function isValidPhone(phone) {
    let numbers = phone.replace(/\D/g, '');

    // Должно быть 11 цифр (с 7) или 10 цифр (без 7)
    if (numbers.startsWith('7') && numbers.length === 11) {
        return true;
    }
    if (numbers.startsWith('8') && numbers.length === 11) {
        return true;
    }

    return false;
}

function formatPhoneOnSave(phone) {
    let numbers = phone.replace(/\D/g, '');




    // СТРОГО 11 цифр


    // Проверяем валидность
    if (!isValidPhone(numbers)) {
        return phone;
    }

    // Нормализуем код страны
    if (numbers.startsWith('8')) {
        numbers = '7' + numbers.substring(1);
    }

    // Форматируем
    return `+7 (${numbers.substring(1, 4)}) ${numbers.substring(4, 7)}-${numbers.substring(7, 9)}-${numbers.substring(9, 11)}`;
}

function updateFieldStyles($container, newValue) {
    // Проверяем, находится ли поле в блоке документов
    if (!$container.closest('.doc_spisok_item').length) {
        return; // Если не в документах - выходим
    }

    const $textElement = $container.find('.doc_spisok_text');
    const $fieldText = $container.find('.field-text');

    // Удаляем старые классы
    $textElement.removeClass('filled-data empty-data');

    if (newValue && newValue !== 'Не указан') {
        // Данные заполнены - зеленый стиль
        $textElement.addClass('filled-data');
        $fieldText.text(newValue).prepend('✅ ');
    } else {
        // Данные пустые - красный стиль  
        $textElement.addClass('empty-data');
        $fieldText.text('Не указан').prepend('❌ ');
    }
}

// ИСПРАВЛЕННАЯ ФУНКЦИЯ updateAnyField
function updateAnyField($container, newValue) {
    const table = $container.data('table');
    const id = $container.data('dialog-id') || $container.data('lead-id');
    const fieldType = $container.data('field-type');

    // ⭐ ПРОВЕРКА ВАЛИДНОСТИ ТЕЛЕФОНА
    if (fieldType === 'phone') {
        let numbers = newValue.replace(/\D/g, '');

        if (!isValidPhone(numbers)) {
            showNotification('❌ Введите корректный номер телефона (10 цифр после +7)', 'error');
            $container.find('.field-input').focus();
            return;
        }

        // Форматируем только если номер валидный
        newValue = formatPhoneOnSave(newValue);
    }

    console.log('💾 Универсальное обновление:', { table, id, fieldType, newValue });

    $.ajax({
        url: ajaxurl,
        type: 'POST',
        data: {
            action: 'update_any_field',
            table: table,
            id: id,
            field_type: fieldType,
            field_value: newValue
        },
        success: function (response) {
            if (response.success) {
                // Восстанавливаем структуру с учетом типа поля
                if (fieldType === 'phone') {
                    $container.find('.field-input, .save-field-btn, .cancel-field-btn').remove();
                    $container.prepend(`
                        <a class="field-text phone-link" href="tel:${newValue}">
                            ${newValue}
                        </a>
                    `);
                } else {
                    $container.find('.field-input, .save-field-btn, .cancel-field-btn').remove();
                    $container.prepend(`<span class="field-text">${newValue}</span>`);
                }

                $container.find('.edit-field-btn').show();

                if ($container.closest('.doc_spisok_item').length) {
                    // ВОССТАНАВЛИВАЕМ ОРИГИНАЛЬНУЮ СТРУКТУРУ
                    const $docText = $container.find('.doc_spisok_text');

                    // УДАЛЯЕМ НОВЫЙ field-text (который создали через prepend)
                    $container.find('.field-text').first().remove();

                    // ⭐ ИСПРАВЛЕНО: ДОБАВЛЯЕМ ПРАВИЛЬНУЮ СТРУКТУРУ ДЛЯ ТЕЛЕФОНА
                    if (fieldType === 'phone') {
                        $docText.empty().append(`
                            <a class="field-text phone-link" href="tel:${newValue}">
                                ${newValue}
                            </a>
                        `);
                    } else {
                        $docText.empty().append(`<span class="field-text">${newValue}</span>`);
                    }

                    // ОБНОВЛЯЕМ СТИЛИ
                    updateFieldStyles($container, newValue);
                }

                showNotification('✅ Изменения сохранены!', 'success');
            } else {
                const originalValue = $container.data('original-value');
                restoreField($container, originalValue, table, fieldType);
                showNotification('❌ ' + response.data, 'error');
            }
        }
    });
}

// ⭐ ОБНОВЛЕННАЯ ФУНКЦИЯ ВОССТАНОВЛЕНИЯ
function restoreField($container, value, table, fieldType) {
    // Для телефона восстанавливаем ссылку
    if (fieldType === 'phone') {
        $container.find('.field-input, .save-field-btn, .cancel-field-btn').remove();
        $container.prepend(`
            <a class="field-text phone-link" href="tel:${value}">
                ${value}
            </a>
        `);
    } else {
        // Для остальных полей - обычный span
        $container.find('.field-input, .save-field-btn, .cancel-field-btn').remove();
        $container.prepend(`<span class="field-text">${value}</span>`);
    }
    $container.find('.edit-field-btn').show();
}

