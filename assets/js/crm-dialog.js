// ==================== CRM DIALOG EMAIL MANAGEMENT ====================

// Инициализация при загрузке страницы
jQuery(document).ready(function ($) {
    // ==================== CRM DIALOG EMAIL MANAGEMENT ====================

    // Обработчик для кнопки редактирования email заявки
    $(document).on('click', '.edit-lead-email-btn', function (e) {
        e.preventDefault();
        e.stopPropagation();

        const $container = $(this).closest('.email-edit-container');
        $container.find('.email-display').hide();
        $container.find('.email-edit').show();
        $container.find('.lead-email-input').focus().select();
    });

    // Обработчик для кнопки сохранения email заявки
    $(document).on('click', '.save-lead-email-btn', function (e) {
        e.preventDefault();
        e.stopPropagation();

        const $container = $(this).closest('.email-edit-container');
        const leadId = $container.data('lead-id');
        const newEmail = $container.find('.lead-email-input').val().trim();

        if (newEmail !== '' && !isValidEmail(newEmail)) {
            showNotification('Введите корректный email', 'error');
            $container.find('.lead-email-input').focus();
            return;
        }

        updateLeadEmail(leadId, newEmail, $container);
    });

    // Обработчик для кнопки отмены редактирования email заявки
    $(document).on('click', '.cancel-lead-email-btn', function (e) {
        e.preventDefault();
        e.stopPropagation();

        const $container = $(this).closest('.email-edit-container');
        $container.find('.email-edit').hide();
        $container.find('.email-display').show();
    });
});

// ==================== ОБРАБОТЧИКИ ДЛЯ ИМЕНИ ЗАЯВКИ ====================

// Обработчик для кнопки редактирования имени заявки

function updateCreateDialogButtonState(leadId) {
    var $createBtn = $('.create-dialog-btn[data-lead-id="' + leadId + '"]');
    var $nameContainer = $('.name-zayv-edit-container[data-lead-id="' + leadId + '"]');
    var currentName = $nameContainer.find('.name-zayv-text').text().trim();
    
    // Если имя заявки не установлено или равно "Не указано"
    if (!currentName || currentName === 'Не указано') {
        $createBtn.prop('disabled', true)
                 .addClass('disabled')
                 .attr('title', 'Сначала укажите имя заявки')
                 .css('cursor', 'not-allowed')
                 .off('mouseenter') // Убираем предыдущие обработчики
                 .on('mouseenter', function() {
                     // Показываем уведомление при наведении на неактивную кнопку
                     showNotification('Сначала укажите имя заявки', 'warning', 2000);
                 });
    } else {
        $createBtn.prop('disabled', false)
                 .removeClass('disabled')
                 .removeAttr('title')
                 .css('cursor', 'pointer')
                 .off('mouseenter'); // Убираем обработчик предупреждения
    }
}

$(document).ready(function() {
    $('.create-dialog-btn').each(function() {
        var leadId = $(this).data('lead-id');
        updateCreateDialogButtonState(leadId);
    });
});



$(document).on('click', '.create-dialog-btn:not(:disabled)', function (e) {
    e.preventDefault();
    var leadId = $(this).data('lead-id');
    var $scenario1 = $('#scenario1-' + leadId);

    // Дополнительная проверка на всякий случай
    var $nameContainer = $('.name-zayv-edit-container[data-lead-id="' + leadId + '"]');
    var currentName = $nameContainer.find('.name-zayv-text').text().trim();
    
    if (!currentName || currentName === 'Не указано') {
        showNotification('Сначала укажите имя заявки', 'error');
        return;
    }

    if ($scenario1.is(':visible')) {
        $('#createDialogForm-' + leadId).show();
    } else {
        $('#createDialogForm2-' + leadId).show();
    }
});


// Обработчик для кнопки сохранения имени заявки
$(document).on('click', '.save-name-zayv-btn', function (e) {
    e.preventDefault();
    e.stopPropagation();

    const $container = $(this).closest('.name-zayv-edit-container');
    const leadId = $container.data('lead-id');
    const newNameZayv = $container.find('.name-zayv-input').val().trim();

    // Показываем индикатор загрузки
    const $saveBtn = $(this);
    const originalText = $saveBtn.html();
    $saveBtn.html('...').prop('disabled', true);

    $.ajax({
        url: crm_ajax.ajaxurl,
        type: 'POST',
        data: {
            action: 'update_zayv_name',
            lead_id: leadId,
            name_zayv: newNameZayv,
        },
        success: function (response) {
            console.log('AJAX ответ:', response);

            if (response.success) {
                console.log('✅ Имя заявки обновлено');

                // ПРОСТО ПОКАЗЫВАЕМ ИМЯ БЕЗ КНОПКИ КАРАНДАША
                const displayName = newNameZayv || 'Не указано';
                $container.find('.name-zayv-display').html(`
                    <span class="name-zayv-text">${displayName}</span>
                `);
                
                $container.find('.name-zayv-edit').hide();
                $container.find('.name-zayv-display').show();
                $container.find('.name-zayv-status').html('');

                // ОБНОВЛЯЕМ СОСТОЯНИЕ КНОПКИ СОЗДАНИЯ ДИАЛОГА
                updateCreateDialogButtonState(leadId);

                showNotification('Имя заявки обновлено', 'success');
            } else {
                console.log('❌ Ошибка обновления имени заявки:', response.data);
                showNotification('Ошибка: ' + response.data, 'error');
            }
        },
        error: function (xhr, status, error) {
            console.log('❌ Ошибка сети при обновлении имени заявки:', error);
            showNotification('Ошибка сети: ' + error, 'error');
        },
        complete: function () {
            // ВОССТАНАВЛИВАЕМ КНОПКУ В ЛЮБОМ СЛУЧАЕ
            $saveBtn.html(originalText).prop('disabled', false);
        }
    });
});

// Обработчик для кнопки отмены редактирования имени заявки
$(document).on('click', '.cancel-name-zayv-btn', function (e) {
    e.preventDefault();
    e.stopPropagation();

    const $container = $(this).closest('.name-zayv-edit-container');
    $container.find('.name-zayv-edit').hide();
    $container.find('.name-zayv-display').show();
    $container.find('.name-zayv-status').html('');
});

// Проверка уникальности при вводе
$(document).on('input', '.name-zayv-input', function (e) {
    const $container = $(this).closest('.name-zayv-edit-container');
    const leadId = $container.data('lead-id');
    const nameZayv = $(this).val().trim();
    
    if (nameZayv.length > 0) {
        checkZayvNameUnique(leadId, nameZayv, $container);
    } else {
        $container.find('.name-zayv-status').html('');
    }
});

$(document).on('click', '.edit-name-zayv-btn', function (e) {
    e.preventDefault();
    e.stopPropagation();

    const $container = $(this).closest('.name-zayv-edit-container');
    $container.find('.name-zayv-display').hide();
    $container.find('.name-zayv-edit').show();
    $container.find('.name-zayv-input').focus().select();
    
    // Очищаем статус
    $container.find('.name-zayv-status').html('');
});

// ==================== ФУНКЦИИ ДЛЯ ИМЕНИ ЗАЯВКИ ====================

function checkZayvNameUnique(leadId, nameZayv, $container) {
    $.ajax({
        url: crm_ajax.ajaxurl,
        type: 'POST',
        data: {
            action: 'check_zayv_name_unique',
            lead_id: leadId,
            name_zayv: nameZayv,
            nonce: crm_ajax.nonce
        },
        success: function (response) {
            if (response.success) {
                const $status = $container.find('.name-zayv-status');
                if (!response.data.unique) {
                    $status.html('<span style="color: #dc3232;">' + response.data.message + '</span>');
                } else {
                    $status.html('<span style="color: #46b450;">Имя доступно</span>');
                }
            }
        }
    });
}



// ==================== ФУНКЦИИ ДЛЯ EMAIL ЗАЯВОК ====================

// В функции updateLeadEmail также добавьте немедленное обновление
function updateLeadEmail(leadId, newEmail, $container) {
    console.log('Обновление email заявки', leadId, 'на', newEmail);

    // Показываем индикатор загрузки
    const $saveBtn = $container.find('.save-lead-email-btn');
    const originalText = $saveBtn.html();
    $saveBtn.html('...').prop('disabled', true);

    $.ajax({
        url: crm_ajax.ajaxurl,
        type: 'POST',
        data: {
            action: 'update_lead_email',
            lead_id: leadId,
            email: newEmail,
            nonce: crm_ajax.nonce
        },
        success: function (response) {
            console.log('AJAX ответ:', response);

            if (response.success) {
                console.log('✅ Email заявки обновлен');

                // Обновляем отображение
                updateLeadEmailDisplay(leadId, newEmail, $container);

                // ОБНОВЛЯЕМ ПОЛЯ ПОЛУЧАТЕЛЯ ВО ВСЕХ ДИАЛОГАХ НЕМЕДЛЕННО
                updateAllRecipientEmailsImmediately(leadId, newEmail);

                showNotification('Email заявки обновлен', 'success');
            } else {
                console.log('❌ Ошибка обновления email заявки:', response.data);
                const errorMessage = response.data || 'Неизвестная ошибка';
                showNotification('Ошибка обновления email: ' + errorMessage, 'error');
            }
        },
        error: function (xhr, status, error) {
            console.log('❌ Ошибка сети при обновлении email заявки:', error);
            console.log('Статус:', status);
            console.log('xhr:', xhr);
            showNotification('Ошибка сети при обновлении email: ' + error, 'error');
        },
        complete: function () {
            // Восстанавливаем кнопку
            $saveBtn.html(originalText).prop('disabled', false);
        }
    });
}

// Функция для немедленного обновления всех полей получателя
function updateAllRecipientEmailsImmediately(leadId, newEmail) {
    console.log('🔄 Немедленное обновление всех полей получателя для заявки:', leadId, 'Email:', newEmail);
    
    const $panel = $('#dialog-panel-' + leadId);
    const $recipientEmails = $panel.find('.recipient-email');
    
    $recipientEmails.each(function() {
        const $input = $(this);
        const $dialogItem = $input.closest('.dialog-item');
        const dialogId = $dialogItem.data('dialog-id');
        
        // Получаем email диалога
        const dialogEmail = getDialogEmail(leadId, dialogId);
        
        // Если у диалога нет своего email, обновляем поле получателя
        if (!dialogEmail || dialogEmail === 'Email не указан') {
            console.log('📧 Немедленно обновляем поле получателя для диалога без своего email:', dialogId);
            $input.val(newEmail);
        } else {
            console.log('📧 Диалог имеет свой email, не обновляем:', dialogId, dialogEmail);
        }
    });
}

// Функция для обновления всех полей получателя в диалогах
function updateAllRecipientEmails(leadId, newEmail) {
    console.log('🔄 Обновление полей получателя для заявки:', leadId, 'Email:', newEmail);
    
    const $panel = $('#dialog-panel-' + leadId);
    const $recipientEmails = $panel.find('.recipient-email');
    
    $recipientEmails.each(function() {
        const $input = $(this);
        const $dialogItem = $input.closest('.dialog-item');
        const dialogId = $dialogItem.data('dialog-id');
        
        // Получаем email диалога
        const dialogEmail = getDialogEmail(leadId, dialogId);
        
        // Если у диалога нет своего email, обновляем поле получателя
        if (!dialogEmail || dialogEmail === 'Email не указан') {
            console.log('📧 Обновляем поле получателя для диалога без своего email:', dialogId);
            $input.val(newEmail);
        } else {
            console.log('📧 Диалог имеет свой email, не обновляем:', dialogId, dialogEmail);
        }
    });
}

// Функция для получения email диалога
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

// --------------------------окно всех получателей
$(document).on('click', '.toggle-emails-btn', function() {
    var $container = $(this).closest('.dialog-email-container');
    var $additionalContainer = $container.next('.additional-emails-container');
    
    if ($additionalContainer.is(':visible')) {
        // Скрываем контейнер
        $additionalContainer.slideUp(200);
        $(this).html('▼').attr('title', 'Показать все email');
    } else {
        // Показываем контейнер
        $additionalContainer.slideDown(200);
        $(this).html('▲').attr('title', 'Скрыть все email');
    }
});

$(document).on('click', '.email_dobav', function(e) {
    e.preventDefault();
    e.stopPropagation();
    
    const $mainContainer = $(this).closest('.dialog-email-container');
    const leadId = $mainContainer.data('lead-id');
    const dialogId = $mainContainer.data('dialog-id');
    
    const $additionalList = $mainContainer.closest('.decode_email_wap').find('.additional-emails-list');
    
    // 🔧 ДОБАВЬ data-атрибуты при создании нового email
  $additionalList.append(`
    <div class="dialog-email-container" 
         data-lead-id="${leadId}" 
         data-dialog-id="${dialogId}"
         data-email-id="">
        <small> <span class="dialog-email-text">Email не указан</span></small>
        <button type="button" class="edit-dialog-email-btn" style="
            background: none;
            border: none;
            cursor: pointer;
            opacity: 0.7;
            font-size: 10px;
            padding: 1px;
            line-height: 1;
        " title="Редактировать email диалога">
            ✏️
        </button>
        <button class="email_dial_del">-</button>
    </div>
`);
    
    // Показываем контейнер с дополнительными email, если он скрыт
    const $additionalContainer = $mainContainer.closest('.decode_email_wap').find('.additional-emails-container');
    if ($additionalContainer.is(':hidden')) {
        $additionalContainer.show();
        $mainContainer.find('.toggle-emails-btn').html('▲').attr('title', 'Скрыть все email');
    }
    
    console.log('➕ DEBUG: Добавлен новый email с данными:', {leadId, dialogId});
});



$(document).on('click', '.additional-emails-container .save-dialog-email-btn', function(e) {
    e.preventDefault();
    e.stopPropagation();
    
    const $container = $(this).closest('.dialog-email-container');
    const $input = $container.find('.dialog-email-input');
    const newEmail = $input.val().trim();
    
    const dialogId = $container.data('dialog-id');
    const emailId = $container.data('email-id');
    const oldEmail = $container.data('original-email'); // ⬅️ СОХРАНИ СТАРЫЙ EMAIL
    
    console.log('💾 DEBUG: Сохранение email:', {
        oldEmail: oldEmail, // ⬅️ ДОБАВЬ ОТЛАДКУ
        newEmail: newEmail, 
        dialogId: dialogId,
        emailId: emailId,
        type: emailId ? 'ОБНОВЛЕНИЕ' : 'ДОБАВЛЕНИЕ'
    });

    if (!newEmail) {
        alert('Введите email');
        return;
    }

    if (!isValidEmail(newEmail)) {
        alert('Введите корректный email');
        return;
    }

    $(this).prop('disabled', true).text('...');

    const action = emailId ? 'update_dialog_additional_email' : 'save_dialog_additional_email';
    const data = {
        action: action,
        email: newEmail,
        nonce: crm_ajax.nonce
    };

    if (emailId) {
        data.email_id = emailId;
    } else {
        data.dialog_id = dialogId;
    }

    $.ajax({
        url: crm_ajax.ajaxurl,
        type: 'POST',
        data: data,
        success: function(response) {
            console.log('✅ DEBUG: Email сохранен:', response);
            
            if (response.success) {
                // Обновляем data-email-id если это было добавление
                if (!emailId && response.data.email_id) {
                    $container.data('email-id', response.data.email_id);
                }
                
                $input.replaceWith(`<span class="dialog-email-text">${newEmail}</span>`);
                $container.find('.save-dialog-email-btn, .cancel-dialog-email-btn').remove();
                $container.find('.edit-dialog-email-btn').show();
                
                // 🔄 РАЗДЕЛЯЕМ ЛОГИКУ ДЛЯ ДОБАВЛЕНИЯ И ОБНОВЛЕНИЯ
                if (emailId) {
                    // ОБНОВЛЕНИЕ - заменяем старый email на новый
                    updateRecipientEmailOnEdit(dialogId, oldEmail, newEmail);
                } else {
                    // ДОБАВЛЕНИЕ - добавляем новый email
                    updateRecipientEmailWithAdditionals(dialogId, newEmail);
                }
                
                showNotification('Email ' + (emailId ? 'обновлен' : 'сохранен'), 'success');
            } else {
                showNotification('Ошибка: ' + response.data, 'error');
                $container.find('.save-dialog-email-btn').prop('disabled', false).text('✓');
            }
        },
        error: function(xhr, status, error) {
            console.log('❌ DEBUG: Ошибка:', error);
            showNotification('Ошибка сети: ' + error, 'error');
            $container.find('.save-dialog-email-btn').prop('disabled', false).text('✓');
        }
    });
});

function updateRecipientEmailOnEdit(dialogId, oldEmail, newEmail) {
    console.log('🔄 Обновление email в поле получателя:', {oldEmail, newEmail});
    
    const $dialogItem = $(`.dialog-item[data-dialog-id="${dialogId}"]`);
    const $recipientEmail = $dialogItem.find('.recipient-email').last();
    
    if ($recipientEmail.length > 0) {
        let currentEmails = $recipientEmail.val().trim();
        
        if (currentEmails !== '') {
            // Заменяем старый email на новый
            const emailsArray = currentEmails.split(',').map(email => email.trim());
            const index = emailsArray.indexOf(oldEmail);
            
            if (index !== -1) {
                emailsArray[index] = newEmail;
                $recipientEmail.val(emailsArray.join(', '));
                console.log('✅ Email заменен в поле получателя:', oldEmail, '→', newEmail);
            } else {
                // Если старый email не найден, просто добавляем новый
                emailsArray.push(newEmail);
                $recipientEmail.val(emailsArray.join(', '));
                console.log('⚠️ Старый email не найден, добавлен новый:', newEmail);
            }
        } else {
            // Если поле пустое - просто добавляем новый email
            $recipientEmail.val(newEmail);
        }
    }
}


function updateRecipientEmailWithAdditionals(dialogId, newEmail) {
    console.log('🔄 Автокопирование дополнительных email для диалога:', dialogId);
    
    const $dialogItem = $(`.dialog-item[data-dialog-id="${dialogId}"]`);
    const $recipientEmail = $dialogItem.find('.recipient-email').last();
    
    if ($recipientEmail.length > 0 && newEmail) {
        // Получаем текущее значение поля
        let currentEmails = $recipientEmail.val().trim();
        
        if (currentEmails === '') {
            // Если поле пустое - просто добавляем новый email
            $recipientEmail.val(newEmail);
        } else {
            // Если в поле уже есть email - добавляем через запятую
            const emailsArray = currentEmails.split(',').map(email => email.trim());
            
            // Проверяем, нет ли уже этого email в списке
            if (!emailsArray.includes(newEmail)) {
                emailsArray.push(newEmail);
                $recipientEmail.val(emailsArray.join(', '));
            }
        }
        
        console.log('✅ Поле получателя обновлено с дополнительными email:', $recipientEmail.val());
    }
}

$(document).on('click', '.email_dial_del', function(e) {
    e.preventDefault();
    e.stopPropagation();
    
    const $container = $(this).closest('.dialog-email-container');
    const emailId = $container.data('email-id');
    const emailText = $container.find('.dialog-email-text').text();
    const dialogId = $container.data('dialog-id');
    
    console.log('🗑️ DEBUG: Удаление email:', {emailId, emailText, dialogId});

    if (!emailId) {
        // Если это новый, несохраненный email - просто удаляем из DOM
        $container.remove();
        // 🔄 УДАЛЯЕМ ИЗ ПОЛЯ ПОЛУЧАТЕЛЯ
        removeEmailFromRecipient(dialogId, emailText);
        checkAdditionalEmailsContainer();
        return;
    }

    // Подтверждение удаления
    if (!confirm(`Удалить email: ${emailText}?`)) {
        return;
    }

    // Показываем загрузку
    $(this).prop('disabled', true).text('...');

    $.ajax({
        url: crm_ajax.ajaxurl,
        type: 'POST',
        data: {
            action: 'delete_dialog_additional_email',
            email_id: emailId,
            nonce: crm_ajax.nonce
        },
        success: function(response) {
            console.log('✅ DEBUG: Email удален:', response);
            
            if (response.success) {
                // 🔄 УДАЛЯЕМ ИЗ ПОЛЯ ПОЛУЧАТЕЛЯ ПЕРЕД УДАЛЕНИЕМ ИЗ DOM
                removeEmailFromRecipient(dialogId, emailText);
                
                // Удаляем контейнер из DOM
                $container.remove();
                showNotification('Email удален', 'success');
                
                // Проверяем, нужно ли скрыть контейнер
                checkAdditionalEmailsContainer();
            } else {
                showNotification('Ошибка: ' + response.data, 'error');
                $container.find('.email_dial_del').prop('disabled', false).text('-');
            }
        },
        error: function(xhr, status, error) {
            console.log('❌ DEBUG: Ошибка удаления:', error);
            showNotification('Ошибка сети: ' + error, 'error');
            $container.find('.email_dial_del').prop('disabled', false).text('-');
        }
    });
});


// 🔄 ФУНКЦИЯ ДЛЯ УДАЛЕНИЯ EMAIL ИЗ ПОЛЯ ПОЛУЧАТЕЛЯ
function removeEmailFromRecipient(dialogId, emailToRemove) {
    console.log('🔄 Удаление email из поля получателя:', emailToRemove);
    
    const $dialogItem = $(`.dialog-item[data-dialog-id="${dialogId}"]`);
    const $recipientEmail = $dialogItem.find('.recipient-email').last();
    
    if ($recipientEmail.length > 0) {
        let currentEmails = $recipientEmail.val().trim();
        
        if (currentEmails !== '') {
            // Удаляем email из списка
            const emailsArray = currentEmails.split(',').map(email => email.trim());
            const filteredEmails = emailsArray.filter(email => email !== emailToRemove && email !== '');
            
            $recipientEmail.val(filteredEmails.join(', '));
            console.log('✅ Email удален из поля получателя. Осталось:', filteredEmails.join(', '));
        }
    }
}

function checkAdditionalEmailsContainer() {
    const $additionalContainer = $('.additional-emails-container');
    const $additionalList = $('.additional-emails-list');
    
    if ($additionalList.children().length === 0) {
        $additionalContainer.hide();
        // Возвращаем стрелку в исходное состояние
        $('.toggle-emails-btn').html('▼').attr('title', 'Показать все email');
    }
}




function updateLeadEmailDisplay(leadId, email, $container) {
    // Обновляем отображение email
    const $display = $container.find('.email-display');

    if (email) {
        $display.html(`
            <a href="mailto:${email}" class="email-link">${email}</a>
            <button type="button" 
                    class="edit-lead-email-btn" 
                    style="
                        background: none;
                        border: none;
                        cursor: pointer;
                        opacity: 0.7;
                        font-size: 12px;
                        padding: 2px;
                    "
                    title="Редактировать email заявки">
                ✏️
            </button>
        `);
    } else {
        $display.html(`
            <span class="no-email">Не указан</span>
            <button type="button" 
                    class="edit-lead-email-btn" 
                    style="
                        background: none;
                        border: none;
                        cursor: pointer;
                        opacity: 0.7;
                        font-size: 12px;
                        padding: 2px;
                    "
                    title="Добавить email заявки">
                ✏️
            </button>
        `);
    }

    // Скрываем форму редактирования и показываем отображение
    $container.find('.email-edit').hide();
    $container.find('.email-display').show();
}




// ==================== ОБНОВЛЕННАЯ ФУНКЦИЯ ОТКРЫТИЯ ДИАЛОГА ====================

function openCloseDialog(leadId, dialogId, event) {
    console.log('=== openCloseDialog вызван:', leadId, dialogId);

    // Проверяем, редактируется ли сейчас email в этом диалоге
    const $dialogContainer = $(`.dialog-email-container[data-lead-id="${leadId}"][data-dialog-id="${dialogId}"]`);
    const isEditingEmail = $dialogContainer.find('.dialog-email-input').length > 0;

    if (isEditingEmail) {
        console.log('Идет редактирование email - игнорируем открытие/закрытие диалога');
        return;
    }

    // Проверяем, был ли клик на карандаш редактирования email
    if (event && event.target && (
        event.target.classList.contains('edit-dialog-email-btn') ||
        event.target.closest('.edit-dialog-email-btn')
    )) {
        console.log('Клик на карандаш - игнорируем открытие/закрытие диалога');
        return;
    }

    if (event) {
        event.stopPropagation();
        event.preventDefault();
    }



    initDialogsState(leadId);

    const numericDialogId = parseInt(dialogId);
    const numericCurrentId = dialogsState[leadId].currentDialogId ? parseInt(dialogsState[leadId].currentDialogId) : null;

    if (numericCurrentId === numericDialogId) {
        // Закрываем диалог
        dialogsState[leadId].currentDialogId = null;
        console.log('Закрываем диалог:', dialogId);
    } else {
        // Открываем диалог и ПОКАЗЫВАЕМ СООБЩЕНИЯ СРАЗУ
        dialogsState[leadId].currentDialogId = numericDialogId;

        // Автоматически показываем сообщения при открытии диалога
        const dialog = dialogsState[leadId].dialogs.find(d => parseInt(d.id) == numericDialogId);
        if (dialog) {
            dialog.messagesExpanded = true;
        }

        console.log('Открываем диалог:', dialogId, 'и показываем сообщения');
        
        // ОБНОВЛЯЕМ ПОЛЕ ПОЛУЧАТЕЛЯ ПРИ ОТКРЫТИИ ДИАЛОГА
        setTimeout(() => {
            updateRecipientEmailForDialog(leadId, dialogId);
        }, 100);

        // ⭐ ДОБАВЛЯЕМ ЗАГРУЗКУ ФАЙЛОВ ПРИ ОТКРЫТИИ ДИАЛОГА
        setTimeout(() => {
            console.log('📁 Загружаем файлы для открытого диалога:', leadId, dialogId);
            addFileToMessage(null, null, null, null);
        }, 300);
    }

    

    renderDialogs(leadId);
}


// Обработчик для кнопки обновления файлов
$(document).on('click', '.updated_mes_files', function() {
    var $button = $(this);
    var $container = $(this).closest('.attachments-container');
    var leadId = $container.find('.attachments-list').data('lead-id');
    var dialogId = $container.find('.attachments-list').data('dialog-id');
    
    if (leadId && dialogId) {
        console.log('🔄 Принудительная загрузка файлов для:', leadId, dialogId);
        
        // Показываем индикатор загрузки
        $container.find('.attachments-list').html('<div style="text-align: center; color: #666; font-style: italic;">Загрузка файлов...</div>');
        $button.text('Загрузка...').prop('disabled', true);
        
        // Вызываем твою функцию загрузки файлов
        addFileToMessage(null, null, null, null);
        
        // Включаем кнопку обратно через 2 секунды
        setTimeout(() => {
            $button.text('Посмотреть файлы').prop('disabled', false);
        }, 2000);
    } else {
        console.log('❌ Не найдены leadId или dialogId');
        $button.text('Ошибка: данные не найдены');
        setTimeout(() => {
            $button.text('Посмотреть файлы');
        }, 2000);
    }
});

function addFileToMessage(leadId, fileUrl, fileName, fileType, fileModifiedTime = null, highlightFileName = null) {
    console.log('📎 DEBUG: Добавление файла к сообщению:', fileName);
    
    // Находим активный диалог
    var $activeDialog = $('.dialog-item.active');
    if ($activeDialog.length === 0) {
        console.log('❌ ERROR: Активный диалог не найден!');
        alert('Ошибка: активный диалог не найден');
        return;
    }

    console.log('🔍 DEBUG: Активный диалог найден');

    // Ищем контейнер для вложений
    var $attachmentsContainer = $activeDialog.find('.attachments-container');
    var $attachmentsList = $attachmentsContainer.find('.attachments-list');

    // Функция для форматирования времени
    function formatFileTime(modifiedTime) {
        console.log('🕒 DEBUG: formatFileTime вызван с:', modifiedTime);
        
        if (!modifiedTime) {
            console.log('❌ DEBUG: modifiedTime пустой или undefined');
            return 'dd:mm:yyyy:hh:mi';
        }
        
        try {
            // УМНОЖАЕМ НА 1000 - ЭТО ГЛАВНОЕ ИСПРАВЛЕНИЕ!
            var date = new Date(modifiedTime * 1000);
            
            console.log('📅 DEBUG: Создан объект Date:', date);
            
            if (isNaN(date.getTime())) {
                console.log('❌ DEBUG: Дата невалидна');
                return 'dd:mm:yyyy:hh:mi';
            }
            
            var day = String(date.getDate()).padStart(2, '0');
            var month = String(date.getMonth() + 1).padStart(2, '0');
            var year = date.getFullYear();
            var hours = String(date.getHours()).padStart(2, '0');
            var minutes = String(date.getMinutes()).padStart(2, '0');
            
            var result = day + ':' + month + ':' + year + ':' + hours + ':' + minutes;
            console.log('✅ DEBUG: Результат:', result);
            
            return result;
            
        } catch (error) {
            console.log('❌ DEBUG: Ошибка:', error);
            return 'dd:mm:yyyy:hh:mi';
        }
    }

    // Если контейнер не найден, создаем его и ЗАГРУЖАЕМ СУЩЕСТВУЮЩИЕ ФАЙЛЫ
    if ($attachmentsContainer.length === 0) {
        console.log('🆕 DEBUG: Создаем контейнер и загружаем файлы');
    } else {
      
        console.log('✅ DEBUG: Контейнер уже существует, загружаем файлы');
        $attachmentsList = $attachmentsContainer.find('.attachments-list');

        // Загружаем существующие файлы
        var leadId = $activeDialog.find('[data-lead-id]').data('lead-id');
        var dialogId = $activeDialog.find('[data-dialog-id]').data('dialog-id');

        if (leadId && dialogId) {
            console.log('📁 DEBUG: Загружаем файлы для существующего контейнера:', leadId, dialogId);

            $.ajax({
                url: '/crm_files.php',
                type: 'POST',
                data: {
                    action: 'get_dialog_files',
                    lead_id: leadId,
                    dialog_id: dialogId,
                      highlight_file: highlightFileName || fileName 
                },
           success: function (response) {
    console.log('📄 DEBUG: Ответ при загрузке в существующий контейнер:', response);

     console.log('🔍 DEBUG: Ответ сервера:', {
        files: response.files,
        highlight_file: response.highlight_file
    });


    if (response.success && response.files && response.files.length > 0) {
        console.log('✅ DEBUG: Загружено файлов в существующий контейнер:', response.files.length);

        // Очищаем и добавляем файлы
        $attachmentsList.empty();

        response.files.forEach(function (file) {
              console.log('📄 Файл:', file.name, 'Подсветка:', file.highlight);
            var fileIcon = getFileIcon(file.type);
            var formattedTime = formatFileTime(file.modified_time || file.uploaded_time);
 

var fileHtml = '<div class="attachment-item vvod" data-file-url="' + file.url + '" data-file-name="' + file.name + '" ">' +
    '<div style="display: flex; align-items: center; gap: 8px; flex: 1;">' +
    '<span class="attachment-icon" style="font-size: 16px;">' + fileIcon + '</span>' +
    '<span class="attachment-name-display" style="">' + file.name + '</span>' +
    '</div>' +
    '<div style="display: flex; align-items: center; gap: 5px;">' +
    '<time class="mes_file_time">' + formattedTime + '</time>' +
    '<a href="' + file.url + '" target="_blank" class="view-attachment" title="Просмотреть" style="text-decoration: none; font-size: 16px; padding: 4px;">👁️</a>' +
    '<button title="Удалить" class=" mes_file_delet">🗑️</button>' +
    '<button type="button" class="remove-attachment" title="Скрыть" style="background: #ff4444; color: white; border: none; border-radius: 50%; width: 24px; height: 24px; cursor: pointer; font-size: 14px; display: flex; align-items: center; justify-content: center;">×</button>' +
    '</div>' +
    '</div>';
                

            $attachmentsList.append(fileHtml);
        });

        // 🔥 УБИРАЕМ ПОДСВЕТКУ ЧЕРЕЗ 5 СЕКУНД
        if (fileName) {
            setTimeout(() => {
                var $highlightedFile = $attachmentsList.find('.attachment-item').filter(function() {
                    return $(this).data('file-name') === fileName;
                });
                if ($highlightedFile.length > 0) {
                    $highlightedFile.find('.mes_file_time').css({
                        'color': '',
                        'font-weight': '',
                        'background': '',
                        'padding': '',
                        'border-radius': ''
                    });
                    console.log('⚪ DEBUG: Сброс подсветки для файла:', fileName);
                }
            }, 5000);
        }

    } else {
        console.log('ℹ️ DEBUG: Файлов нет для существующего контейнера');
        $attachmentsList.html('<div style="text-align: center; color: #666; font-style: italic;">Нет прикрепленных файлов</div>');
    }
},
                error: function (xhr, status, error) {
                    console.log('❌ DEBUG: Ошибка загрузки в существующий контейнер:', error);
                }
            });
        }
    }

    // Добавляем новый файл (если он есть)
    if (fileUrl && fileName) {
        console.log('➕ DEBUG: Добавляем новый файл:', fileName);
        console.log('✅ DEBUG: Новый файл добавлен в интерфейс');
        showNotification('Файл "' + fileName + '" добавлен к сообщению', 'success');
    }
}




// Функция для обновления поля получателя при открытии диалога
function updateRecipientEmailForDialog(leadId, dialogId) {
    console.log('🔄 Обновление поля получателя для диалога:', dialogId);
    
    const $dialogItem = $(`.dialog-item[data-dialog-id="${dialogId}"]`);
    const $recipientEmail = $dialogItem.find('.recipient-email').last();
    
    if ($recipientEmail.length === 0) {
        console.log('❌ Поле получателя не найдено в диалоге:', dialogId);
        return;
    }
    
    // Получаем email диалога
    const dialogEmail = getDialogEmail(leadId, dialogId);
    
    // Получаем email заявки
    const leadEmail = getLeadEmail(leadId);
    
    let emailToSet = '';
    
    if (dialogEmail && dialogEmail !== 'Email не указан') {
        // Если у диалога есть свой email - используем его
        emailToSet = dialogEmail;
        console.log('📧 Используем email диалога:', dialogEmail);
    } else if (leadEmail) {
        // Если у диалога нет своего email, но есть email заявки - используем его
        emailToSet = leadEmail;
        console.log('📧 Используем email заявки:', leadEmail);
    }
    
    // Устанавливаем значение только если поле пустое или содержит старый email
    const currentValue = $recipientEmail.val().trim();
    if (!currentValue || currentValue === leadEmail) {
        $recipientEmail.val(emailToSet);
        console.log('✅ Поле получателя обновлено:', emailToSet);
    } else {
        console.log('📧 Поле получателя уже заполнено другим email, не изменяем:', currentValue);
    }
}


// ==================== ОБНОВЛЕННАЯ ФУНКЦИЯ ОБНОВЛЕНИЯ EMAIL ДИАЛОГА ====================



function updateDialogEmail(leadId, dialogId, newEmail, $container) {
    console.log('Обновление email диалога', dialogId, 'на', newEmail);

    // Показываем индикатор загрузки
    const $saveBtn = $container.find('.save-dialog-email-btn');
    const originalText = $saveBtn.html();
    $saveBtn.html('...').prop('disabled', true);

    // 🔧 СОХРАНЯЕМ СТАРЫЙ EMAIL (может быть пустым или "Email не указан")
    const oldEmail = $container.data('original-email'); // ⬅️ Используем сохраненный при редактировании
    const oldDisplayEmail = $container.find('.dialog-email-text').text();

    $.ajax({
        url: crm_ajax.ajaxurl,
        type: 'POST',
        data: {
            action: 'update_dialog_email',
            lead_id: leadId,
            dialog_id: dialogId,
            email: newEmail,
            nonce: crm_ajax.nonce
        },
        success: function (response) {
            console.log('AJAX ответ:', response);

            if (response.success) {
                console.log('✅ Email диалога обновлен');

                // Обновляем отображение
                const displayEmail = newEmail || 'Email не указан';
                $container.find('.dialog-email-input, .save-dialog-email-btn, .cancel-dialog-email-btn').remove();
                $container.prepend(`<small> <span class="dialog-email-text">${displayEmail}</span></small>`);
                $container.find('.edit-dialog-email-btn').show();

                // Обновляем глобальное состояние
                if (dialogsState[leadId] && dialogsState[leadId].dialogs) {
                    const dialog = dialogsState[leadId].dialogs.find(d => parseInt(d.id) === parseInt(dialogId));
                    if (dialog) {
                        dialog.email = newEmail;
                    }
                }

                // 🔄 УМНОЕ ОБНОВЛЕНИЕ ГЛАВНОГО EMAIL В ПОЛЕ ПОЛУЧАТЕЛЯ
                console.log('🔄 Обновление главного email:', {oldEmail, oldDisplayEmail, newEmail});
                
                const $dialogItem = $(`.dialog-item[data-dialog-id="${dialogId}"]`);
                const $recipientEmail = $dialogItem.find('.recipient-email').last();
                
                if ($recipientEmail.length > 0 && newEmail) {
                    let currentEmails = $recipientEmail.val().trim();
                    
                    if (currentEmails === '') {
                        // Если поле пустое - просто добавляем новый email
                        $recipientEmail.val(newEmail);
                    } else {
                        const emailsArray = currentEmails.split(',').map(email => email.trim());
                        
                        // 🔧 УМНЫЙ ПОИСК ГЛАВНОГО EMAIL ДЛЯ ЗАМЕНЫ
                        let mainEmailReplaced = false;
                        
                        // Вариант 1: Ищем по сохраненному оригинальному email
                        if (oldEmail && oldEmail !== 'Email не указан') {
                            const oldIndex = emailsArray.indexOf(oldEmail);
                            if (oldIndex !== -1) {
                                emailsArray[oldIndex] = newEmail;
                                mainEmailReplaced = true;
                                console.log('✅ Заменен старый главный email:', oldEmail, '→', newEmail);
                            }
                        }
                        
                        // Вариант 2: Если не нашли, ищем первый email который есть в основном поле
                        if (!mainEmailReplaced && oldDisplayEmail && oldDisplayEmail !== 'Email не указан') {
                            const displayIndex = emailsArray.indexOf(oldDisplayEmail);
                            if (displayIndex !== -1) {
                                emailsArray[displayIndex] = newEmail;
                                mainEmailReplaced = true;
                                console.log('✅ Заменен отображаемый главный email:', oldDisplayEmail, '→', newEmail);
                            }
                        }
                        
                        // Вариант 3: Если все еще не нашли, добавляем новый в начало
                        if (!mainEmailReplaced) {
                            // Удаляем возможные дубликаты нового email
                            emailsArray = emailsArray.filter(email => email !== newEmail);
                            // Добавляем новый главный email в начало
                            emailsArray.unshift(newEmail);
                            console.log('✅ Добавлен новый главный email в начало:', newEmail);
                        }
                        
                        $recipientEmail.val(emailsArray.join(', '));
                    }
                    
                    console.log('✅ Итоговое поле получателя:', $recipientEmail.val());
                }

                showNotification('Email диалога обновлен', 'success');
            } else {
                showNotification('Ошибка обновления email: ' + response.data, 'error');
            }
        },
        error: function (xhr, status, error) {
            showNotification('Ошибка сети: ' + error, 'error');
        },
        complete: function () {
            $saveBtn.html(originalText).prop('disabled', false);
        }
    });
}
// Функция для немедленного обновления поля получателя
function updateRecipientEmailImmediately(leadId, dialogId, newEmail) {
    console.log('🔄 Немедленное обновление поля получателя для диалога:', dialogId, 'Email:', newEmail);
    
    const $dialogItem = $(`.dialog-item[data-dialog-id="${dialogId}"]`);
    const $recipientEmail = $dialogItem.find('.recipient-email').last();
    
    if ($recipientEmail.length === 0) {
        console.log('❌ Поле получателя не найдено в диалоге:', dialogId);
        return;
    }
    
    // Получаем текущее значение поля
    const currentValue = $recipientEmail.val().trim();
    
    // Получаем email заявки для сравнения
    const leadEmail = getLeadEmail(leadId);
    
    // Логика обновления:
    // 1. Если поле пустое - обновляем
    // 2. Если поле содержит старый email заявки - обновляем
    // 3. Если поле содержит старый email диалога - обновляем
    // 4. Если поле содержит другой email - НЕ обновляем
    
    let shouldUpdate = false;
    
    if (!currentValue) {
        // Поле пустое - обновляем
        shouldUpdate = true;
        console.log('📧 Поле пустое - обновляем');
    } else if (currentValue === leadEmail) {
        // Поле содержит email заявки - обновляем
        shouldUpdate = true;
        console.log('📧 Поле содержит email заявки - обновляем');
    } else {
        // Проверяем, содержит ли поле старый email диалога
        const oldDialogEmail = getDialogEmail(leadId, dialogId);
        if (oldDialogEmail && currentValue === oldDialogEmail) {
            // Поле содержит старый email диалога - обновляем
            shouldUpdate = true;
            console.log('📧 Поле содержит старый email диалога - обновляем');
        } else {
            // Поле содержит другой email - не обновляем
            console.log('📧 Поле содержит другой email, не обновляем:', currentValue);
        }
    }
    
    if (shouldUpdate && newEmail) {
        $recipientEmail.val(newEmail);
        console.log('✅ Поле получателя немедленно обновлено:', newEmail);
    }
}

// ==================== ВСПОМОГАТЕЛЬНЫЕ ФУНКЦИИ ====================

function isValidEmail(email) {
    if (email === '') return true; // Пустой email допустим
    const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    return emailRegex.test(email);
}



// ==================== ФУНКЦИЯ ДЛЯ ПОКАЗА УВЕДОМЛЕНИЙ ====================
function showNotification(message, type) {
    console.log('🔔 DEBUG: showNotification вызвана:', { message, type });
    
    // Удаляем предыдущие уведомления
    $('.crm-notification').remove();
    
    // Создаем элемент уведомления
    const $notification = $('<div class="crm-notification notice notice-' + type + '">' + message + '</div>');
    
    // Добавляем в тело документа
    $('body').append($notification);
    
    // Стили с максимальным приоритетом
    $notification.css({
        'position': 'fixed',
        'top': '20px',
        'right': '20px',
        'z-index': '99999',
        'padding': '15px 20px',
        'border-radius': '8px',
        'box-shadow': '0 4px 12px rgba(0,0,0,0.3)',
        'font-size': '14px',
        'font-weight': 'bold',
        'min-width': '300px',
        'text-align': 'center',
        'background': type === 'success' ? '#28a745' : 
                     type === 'error' ? '#dc3545' : 
                     type === 'warning' ? '#ffc107' : '#0073aa',
        'color': type === 'warning' ? '#000' : '#fff',
        'border': type === 'success' ? '2px solid #1e7e34' : 
                 type === 'error' ? '2px solid #c82333' : 
                 type === 'warning' ? '2px solid #e0a800' : '2px solid #0056b3'
    });
    
    // Автоматически скрываем через 5 секунд
    setTimeout(function () {
        $notification.fadeOut(300, function () {
            $(this).remove();
        });
    }, 5000);
}

// ==================== ОБРАБОТЧИКИ ДЛЯ КАРАНДАША EMAIL ДИАЛОГА ====================

console.log('=== CRM DEBUG: Loading email handlers ===');


// Обработчик для карандаша редактирования email диалога
$(document).on('click', '.edit-dialog-email-btn', function(e) {
    console.log('🖊️ DEBUG: Карандаш нажат!', this);
    e.preventDefault();
    e.stopPropagation();
    
    const $container = $(this).closest('.dialog-email-container');
    console.log('🖊️ DEBUG: Контейнер найден:', $container.length);
    
    const $emailText = $container.find('.dialog-email-text');
    const currentEmail = $emailText.text();
    console.log('🖊️ DEBUG: Текущий email:', currentEmail);
    
    const displayEmail = currentEmail === 'Email не указан' ? '' : currentEmail;
    
    // Сохраняем оригинальный email в data-атрибут
    $container.data('original-email', currentEmail);
    
    // Заменяем текст на input
    $emailText.replaceWith(`
        <input type="email" 
               class="dialog-email-input" 
               value="${displayEmail}"
               placeholder="Введите email"
               style="font-size: 11px; padding: 1px 3px; height: 18px; width: 150px;">
        <button type="button" 
                class="save-dialog-email-btn button button-small button-primary"
                style="padding: 1px 4px; font-size: 10px; height: 18px; line-height: 1;">
            ✓
        </button>
        <button type="button" 
                class="cancel-dialog-email-btn button button-small"
                style="padding: 1px 4px; font-size: 10px; height: 18px; line-height: 1;">
            ✕
        </button>
    `);
    
    // Скрываем карандаш
    $(this).hide();
    
    console.log('🖊️ DEBUG: Поле редактирования создано, оригинальный email сохранен:', currentEmail);
});


// Обработчик для сохранения email диалога
$(document).on('click', '.email_glav .save-dialog-email-btn', function(e) {
    console.log('💾 DEBUG: Сохранение email нажато!', this);
    e.preventDefault();
    e.stopPropagation();
    
    const $container = $(this).closest('.dialog-email-container');
    const leadId = $container.data('lead-id');
    const dialogId = $container.data('dialog-id');
    const newEmail = $container.find('.dialog-email-input').val().trim();
    
    console.log('💾 DEBUG: Данные для сохранения:', {leadId, dialogId, newEmail});
    
    if (newEmail !== '' && !isValidEmail(newEmail)) {
        console.log('❌ DEBUG: Невалидный email');
        showNotification('Введите корректный email', 'error');
        $container.find('.dialog-email-input').focus();
        return;
    }
    
    updateDialogEmail(leadId, dialogId, newEmail, $container);
});

// Обработчик для отмены редактирования email диалога

$(document).on('click', '.cancel-dialog-email-btn', function(e) {
    console.log('❌ DEBUG: Отмена редактирования');
    e.preventDefault();
    e.stopPropagation();
    
    const $container = $(this).closest('.dialog-email-container');
    
    // ВОССТАНАВЛИВАЕМ ОРИГИНАЛЬНЫЙ EMAIL ИЗ DATA-АТРИБУТА
    const originalEmail = $container.data('original-email') || 'Email не указан';
    
    console.log('❌ DEBUG: Восстанавливаем оригинальный email:', originalEmail);
    
    // Удаляем поля редактирования и восстанавливаем оригинальный текст
    $container.find('.dialog-email-input, .save-dialog-email-btn, .cancel-dialog-email-btn').remove();
    $container.prepend(`<span class="dialog-email-text">${originalEmail}</span>`);
    $container.find('.edit-dialog-email-btn').show();
    
    // Очищаем сохраненный оригинальный email
    $container.removeData('original-email');
});


// ==================== НОВЫЕ ОБРАБОТЧИКИ ДЛЯ АВТОЗАПОЛНЕНИЯ ====================

// 1. Функция проверки временного email
function isTemporaryEmail(email) {
    if (!email) return false;
    const temporaryPatterns = [
        /^[a-zA-Z0-9._%+-]*@?[a-zA-Z0-9.-]*\.?[a-zA-Z]*$/,
        /@example\./i,
        /test@/i,
        /temp@/i,
    ];
    return temporaryPatterns.some(pattern => pattern.test(email)) || !isValidEmail(email);
}

// 2. Функция обновления получателя при вводе
function updateRecipientEmailOnInput(leadId, dialogId, newEmail) {
    console.log('🔄 Обновление поля получателя при вводе:', {leadId, dialogId, newEmail});
    if (!newEmail) return;
    
    const $dialogItem = $(`.dialog-item[data-dialog-id="${dialogId}"]`);
    const $recipientEmail = $dialogItem.find('.recipient-email').last();
    if ($recipientEmail.length === 0) return;
    
    const currentValue = $recipientEmail.val().trim();
    const leadEmail = getLeadEmail(leadId);
    
    let shouldUpdate = false;
    if (!currentValue) {
        shouldUpdate = true;
    } else if (currentValue === leadEmail) {
        shouldUpdate = true;
    } else {
        const oldDialogEmail = getDialogEmail(leadId, dialogId);
        if (oldDialogEmail && currentValue === oldDialogEmail) {
            shouldUpdate = true;
        } else if (isTemporaryEmail(currentValue)) {
            shouldUpdate = true;
        }
    }
    
    if (shouldUpdate) {
        $recipientEmail.val(newEmail);
        console.log('✅ Поле получателя обновлено при вводе:', newEmail);
    }
}



// 4. Обработчик автозаполнения email заявки  
$(document).on('change input paste', '.lead-email-input', function(e) {
    console.log('🔄 Событие автозаполнения email заявки:', e.type);
    const $input = $(this);
    const $container = $input.closest('.email-edit-container');
    const leadId = $container.data('lead-id');
    const newEmail = $input.val().trim();
    
    // Обновляем все поля получателей для этой заявки
    const $panel = $('#dialog-panel-' + leadId);
    const $recipientEmails = $panel.find('.recipient-email');
    
    $recipientEmails.each(function() {
        const $recipientInput = $(this);
        const $dialogItem = $recipientInput.closest('.dialog-item');
        const dialogId = $dialogItem.data('dialog-id');
        
        // Получаем email диалога
        const dialogEmail = getDialogEmail(leadId, dialogId);
        
        // Если у диалога нет своего email, обновляем поле получателя
        if (!dialogEmail || dialogEmail === 'Email не указан') {
            const currentValue = $recipientInput.val().trim();
            if (!currentValue || currentValue === getLeadEmail(leadId)) {
                $recipientInput.val(newEmail);
                console.log('📧 Обновлено поле получателя при вводе для диалога:', dialogId);
            }
        }
    });
});

// 5. Обработчик автозаполнения браузера
$(document).on('animationstart', '.dialog-email-input, .lead-email-input', function(e) {
    if (e.animationName === 'onAutoFillStart') {
        console.log('🎯 Обнаружено автозаполнение браузера');
        setTimeout(() => {
            const $input = $(this);
            if ($input.hasClass('dialog-email-input')) {
                const $container = $input.closest('.dialog-email-container');
                const leadId = $container.data('lead-id');
                const dialogId = $container.data('dialog-id');
                const newEmail = $input.val().trim();
                updateRecipientEmailOnInput(leadId, dialogId, newEmail);
            } else if ($input.hasClass('lead-email-input')) {
                const $container = $input.closest('.email-edit-container');
                const leadId = $container.data('lead-id');
                const newEmail = $input.val().trim();
                
                // Обновляем все получатели
                const $panel = $('#dialog-panel-' + leadId);
                const $recipientEmails = $panel.find('.recipient-email');
                $recipientEmails.each(function() {
                    const $recipientInput = $(this);
                    const $dialogItem = $recipientInput.closest('.dialog-item');
                    const dialogId = $dialogItem.data('dialog-id');
                    const dialogEmail = getDialogEmail(leadId, dialogId);
                    
                    if (!dialogEmail || dialogEmail === 'Email не указан') {
                        const currentValue = $recipientInput.val().trim();
                        if (!currentValue || currentValue === getLeadEmail(leadId)) {
                            $recipientInput.val(newEmail);
                        }
                    }
                });
            }
        }, 100);
    }
});


$(document).on('click', '.mes_file_delet', function(e) {
    e.preventDefault();
    e.stopPropagation();
    
    var $attachmentItem = $(this).closest('.attachment-item');
    var fileName = $attachmentItem.data('file-name');
    var $attachmentsList = $attachmentItem.closest('.attachments-list');
    
    var leadId = $attachmentsList.data('lead-id');
    var dialogId = $attachmentsList.data('dialog-id');
    
    console.log('🗑️ DEBUG: Удаление файла:', { fileName, leadId, dialogId });
    
    if (!confirm('Удалить файл "' + fileName + '"? Это действие нельзя отменить.')) {
        return;
    }
    
    // Показываем загрузку
    var $deleteBtn = $(this);
    var originalHtml = $deleteBtn.html();
    $deleteBtn.html('⏳').prop('disabled', true);
    
    // 🔥 ПРАВИЛЬНЫЙ ВАРИАНТ 1: Передаем оригинальное имя
    $.ajax({
        url: '/crm_files.php',
        type: 'POST',
        data: {
            action: 'delete_dialog_file',
            lead_id: leadId,
            dialog_id: dialogId,
            file_name: fileName // ПЕРЕДАЕМ ОРИГИНАЛЬНОЕ ИМЯ
        },
        success: function(response) {
            console.log('🗑️ DEBUG: Ответ сервера:', response);
            
            if (response.success) {
                // Удаляем файл из интерфейса
                $attachmentItem.fadeOut(300, function() {
                    $(this).remove();
                    
                    // Проверяем остались ли файлы
                    if ($attachmentsList.find('.attachment-item').length === 0) {
                        $attachmentsList.html('<div style="text-align: center; color: #666; font-style: italic;">Нет прикрепленных файлов</div>');
                    }
                });
                
                showNotification('Файл "' + fileName + '" удален', 'success');
            } else {
                showNotification('Ошибка удаления: ' + response.error, 'error');
                $deleteBtn.html(originalHtml).prop('disabled', false);
            }
        },
        error: function(xhr, status, error) {
            console.log('❌ DEBUG: Ошибка AJAX:', {
                status: status,
                error: error,
                xhr: xhr
            });
            
            showNotification('Ошибка сети: ' + status + ' - ' + error, 'error');
            $deleteBtn.html(originalHtml).prop('disabled', false);
        }
    });
});


// ==================== ИНИЦИАЛИЗАЦИЯ ====================

console.log('CRM Dialog Email Management initialized');


