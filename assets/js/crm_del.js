// ==================== CRM DELETE DIALOG FUNCTIONS ====================

// Обработчик для кнопки удаления диалога
$(document).on('click', '.dialog_del', function (e) {
    e.preventDefault();
    e.stopPropagation();


    
    // Проверяем что это конкретный диалог, а не все

    

    const $button = $(this);
    console.log('🔍 DEBUG: Button attributes:', {
        dialogId: $button.data('dialog-id'),
        leadId: $button.data('lead-id'),
        allData: $button.data(),
        html: $button.prop('outerHTML')
    });

    const $dialogItem = $button.closest('.dialog-item');
    console.log('🔍 DEBUG: Dialog item attributes:', {
        dialogId: $dialogItem.data('dialog-id'),
        leadId: $dialogItem.data('lead-id'),
        allData: $dialogItem.data()
    });


    // Пробуем разные способы получить leadId
    const dialogId = $button.data('dialog-id') || $dialogItem.data('dialog-id');
    const leadId = $button.data('lead-id') || $dialogItem.data('lead-id') || $button.attr('data-lead-id');

    console.log('🗑️ DEBUG: Final values:', { dialogId, leadId });

    if (!dialogId) {
        showNotification('Ошибка: ID диалога не найден', 'error');
        return;
    }

    // Показываем подтверждение
    if (!confirm('❌ ВНИМАНИЕ!\n\nВы собираетесь удалить диалог и ВСЕ связанные данные:\n\n• Все сообщения диалога\n• Все прикрепленные файлы\n• Папку с файлами на сервере\n• Историю переписки\n\nЭто действие НЕЛЬЗЯ отменить!\n\nПродолжить?')) {
        return;
    }

    // Показываем индикатор загрузки
    $button.html('⏳ Удаление...').prop('disabled', true);

    // Запрос на удаление
    $.ajax({
        url: crm_ajax.ajaxurl,
        type: 'POST',
        data: {
            action: 'delete_dialog',
            dialog_id: dialogId,
        },
        success: function (response) {
            console.log('🗑️ DEBUG: Delete response:', response);

            if (response.success) {
                showNotification('✅ Диалог успешно удален', 'success');

                // Анимация удаления ТОЛЬКО этого диалога
                $dialogItem.addClass('deleting');
                setTimeout(() => {
                    $dialogItem.fadeOut(400, function () {
                        $(this).remove();

                        // Очистка после удаления
                        cleanupAfterDelete(leadId, dialogId);
                    });
                }, 500);

            } else {
                showNotification('❌ Ошибка: ' + response.data, 'error');
                $button.html('Удалить диалог').prop('disabled', false);
            }
        },
        error: function (xhr, status, error) {
            console.log('❌ DEBUG: Delete error:', error);
            showNotification('❌ Ошибка сети при удалении: ' + error, 'error');
            $button.html('Удалить диалог').prop('disabled', false);
        }
    });
});

function getLeadIdFromSomewhere() {
    // Попробуй разные варианты:

    // 1. Из URL
    const urlParams = new URLSearchParams(window.location.search);
    let leadId = urlParams.get('lead_id');

    // 2. Из глобальной переменной
    if (!leadId && typeof currentLeadId !== 'undefined') {
        leadId = currentLeadId;
    }

    // 3. Из активного элемента
    if (!leadId) {
        const $activeLead = $('.lead-item.active, [data-lead-id]').first();
        leadId = $activeLead.data('lead-id');
    }

    console.log('🔍 DEBUG: Found leadId from somewhere:', leadId);
    return leadId;
}

// Функция для показа статистики перед удалением (опционально)
function showDialogDeleteConfirmation(dialogId, leadId) {
    console.log('📊 DEBUG: Getting dialog stats for confirmation:', dialogId);

    $.ajax({
        url: crm_ajax.ajaxurl,
        type: 'POST',
        data: {
            action: 'get_dialog_stats',
            dialog_id: dialogId,
        },
        success: function (response) {
            if (response.success) {
                const stats = response.data;
                showDeleteStatsModal(dialogId, leadId, stats);
            } else {
                // Если не получили статистику, показываем стандартное подтверждение
                confirmDeleteDialog(dialogId, leadId);
            }
        },
        error: function () {
            // При ошибке показываем стандартное подтверждение
            confirmDeleteDialog(dialogId, leadId);
        }
    });
}

// Модальное окно с статистикой (опционально)
function showDeleteStatsModal(dialogId, leadId, stats) {
    const message = `
        ❌ ВНИМАНИЕ! Вы удаляете диалог:\n\n
        • Сообщений: ${stats.messages_count || 0}\n
        • Файлов: ${stats.files_count || 0}\n
        • Папка с файлами: ${stats.folder_exists ? 'ДА' : 'НЕТ'}\n\n
        Это действие НЕЛЬЗЯ отменить!\n\n
        Продолжить?
    `;

    if (confirm(message)) {
        deleteDialog(dialogId, leadId);
    }
}

// Основная функция удаления
function deleteDialog(dialogId, leadId) {
    console.log('🗑️ DEBUG: Starting deletion for dialog:', dialogId);

    const $dialogItem = $(`.dialog-item[data-dialog-id="${dialogId}"]`);
    const $button = $dialogItem.find('.dialog_del');

    // Показываем индикатор загрузки
    $button.html('⏳ Удаление...').prop('disabled', true);

    $.ajax({
        url: crm_ajax.ajaxurl,
        type: 'POST',
        data: {
            action: 'delete_dialog',
            dialog_id: dialogId,
        },
        success: function (response) {
            console.log('🗑️ DEBUG: Delete response:', response);

            if (response.success) {
                showNotification('✅ Диалог успешно удален', 'success');

                // Анимация удаления
                $dialogItem.addClass('deleting');
                setTimeout(() => {
                    $dialogItem.fadeOut(400, function () {
                        $(this).remove();

                        // Обновляем интерфейс после удаления
                        cleanupAfterDelete(leadId, dialogId);
                    });
                }, 500);

            } else {
                showNotification('❌ Ошибка: ' + response.data, 'error');
                $button.html('Удалить диалог').prop('disabled', false);
            }
        },
        error: function (xhr, status, error) {
            console.log('❌ DEBUG: Delete AJAX error:', error);
            showNotification('❌ Ошибка сети: ' + error, 'error');
            $button.html('Удалить диалог').prop('disabled', false);
        }
    });
}


function showNoDialogsMessage(leadId) {
    const $container = $('#dialogs-container-' + leadId); 
    if ($container.length > 0) {
        $container.append('<div class="no-dialogs-message" style="text-align: center; padding: 20px; color: #666;">Нет диалогов</div>');
    }
}

function hideNoDialogsMessage(leadId) {
    $('.no-dialogs-message').remove();
}

// Обновление счетчика диалогов
function updateDialogsCounter(leadId) {
    const $counter = $(`[data-lead-dialogs-count="${leadId}"]`);
    if ($counter.length > 0) {
        const count = $(`.dialog-item[data-lead-id="${leadId}"]`).length;
        $counter.text(count);
    }
}

// Стили для анимации удаления
function addDeleteStyles() {
    if (!$('#crm-delete-styles').length) {
        $('head').append(`
            <style id="crm-delete-styles">
                .dialog-item.deleting {
                    opacity: 0.5;
                    background-color: #ffe6e6;
                    border-left: 3px solid #ff4444;
                }
                .dialog_del:disabled {
                    opacity: 0.6;
                    cursor: not-allowed;
                }
            </style>
        `);
    }
}

// Инициализация при загрузке
$(document).ready(function () {
    addDeleteStyles();
    console.log('CRM Delete Dialog functions initialized');
});
// В функцию cleanupAfterDelete добавить:
function cleanupAfterDelete(leadId, deletedDialogId) {
    console.log('🧹 DEBUG: Cleaning up after delete:', { leadId, deletedDialogId });

    // 1. Очистка глобального состояния
    if (dialogsState[leadId] && dialogsState[leadId].dialogs) {
        dialogsState[leadId].dialogs = dialogsState[leadId].dialogs.filter(
            dialog => parseInt(dialog.id) !== parseInt(deletedDialogId)
        );

        // Если удалили активный диалог - сбрасываем
        if (dialogsState[leadId].currentDialogId == deletedDialogId) {
            dialogsState[leadId].currentDialogId = null;
        }
    }

    // 2. Закрываем панель если удалили активный диалог
    const $activePanel = $('#dialog-panel-' + leadId);
    if ($activePanel.length > 0 && dialogsState[leadId]?.currentDialogId == deletedDialogId) {
        $activePanel.html('<div style="text-align: center; padding: 40px; color: #666;">Диалог удален</div>');
    }

    // 3. Очистка локального хранилища (если используется)
    clearDialogFromStorage(deletedDialogId);

    // 4. Обновляем счетчики
    updateDialogsCounter(leadId);
}

// Очистка из LocalStorage
function clearDialogFromStorage(dialogId) {
    try {
        // Если храните что-то по ключу диалога
        localStorage.removeItem('dialog_' + dialogId);
        sessionStorage.removeItem('dialog_' + dialogId);
    } catch (e) {
        console.log('⚠️ DEBUG: Storage cleanup error:', e);
    }
}

// ==================== CRM DELETE LEAD FUNCTIONS ====================

// Обработчик для кнопки удаления заявки
$(document).on('click', '.zayv_del', function(e) {
    e.preventDefault();
    e.stopPropagation();
    
    const $button = $(this);
    const $leadRow = $button.closest('.lead-row');
    const leadId = $leadRow.data('lead-id');
    
    console.log('🗑️ LEAD DEBUG: Delete lead clicked:', { leadId });
    
    if (!leadId) {
        showNotification('Ошибка: ID заявки не найден', 'error');
        return;
    }
    
    // Показываем серьезное подтверждение
    if (!confirm('🚨 ВНИМАНИЕ КРИТИЧЕСКОЕ УДАЛЕНИЕ!\n\nВы собираетесь удалить ВСЮ ЗАЯВКУ и ВСЕ связанные данные:\n\n• Все диалоги заявки\n• Все сообщения всех диалогов\n• Все файлы всех диалогов\n• Все документы заявки\n• Папки с файлами на сервере\n• Всю историю переписки\n\nЭто действие НЕЛЬЗЯ отменить!\n\nПродолжить?')) {
        return;
    }
    
    // Показываем индикатор загрузки
    $button.html('⏳ Удаление...').prop('disabled', true);
    
    // Запрос на удаление
    $.ajax({
        url: crm_ajax.ajaxurl,
        type: 'POST',
        data: {
            action: 'delete_lead',
            lead_id: leadId,
        },
        success: function(response) {
            console.log('🗑️ LEAD DEBUG: Delete response:', response);
            
            if (response.success) {
                showNotification('✅ Заявка и все связанные данные успешно удалены', 'success');
                
                // Анимация удаления заявки перед перезагрузкой
                $leadRow.addClass('deleting');
                setTimeout(() => {
                    $leadRow.fadeOut(400, function() {
                        // После анимации перезагружаем страницу
                        setTimeout(() => {
                            location.reload();
                        }, 300);
                    });
                }, 500);
                
            } else {
                showNotification('❌ Ошибка удаления заявки: ' + response.data, 'error');
                $button.html('удалить заявку').prop('disabled', false);
            }
        },
        error: function(xhr, status, error) {
            console.log('❌ LEAD DEBUG: Delete error:', error);
            showNotification('❌ Ошибка сети при удалении заявки: ' + error, 'error');
            $button.html('удалить заявку').prop('disabled', false);
        }
    });
});

// Очистка интерфейса после удаления заявки
// ДЕБАГ версия - посмотрим что происходит
function cleanupAfterLeadDelete(leadId) {
    console.log('🧹 DEBUG: Starting cleanup for lead:', leadId);
    
    const $leadRow = $(`.lead-row[data-lead-id="${leadId}"]`);
    console.log('🔍 DEBUG: Found lead row:', $leadRow.length);
    
    if ($leadRow.length === 0) {
        console.log('❌ DEBUG: Lead row not found!');
        return;
    }
    
    // Простая анимация исчезновения
    $leadRow.fadeOut(800, function() {
        console.log('✅ DEBUG: Fadeout complete, removing element');
        $(this).remove();
        
        // Обновляем интерфейс
        updateLeadsCounter();
        
        console.log('🔍 DEBUG: Remaining leads:', $('.lead-row').length);
    });
    
    // Просто меняем opacity у остальных на время анимации
    $('.lead-row').not($leadRow).fadeTo(200, 0.7)
                  .fadeTo(400, 1);
}

// Обновление счетчика заявок
function updateLeadsCounter() {
    const $counter = $('[data-leads-count]');
    if ($counter.length > 0) {
        const count = $('.lead-row').length;
        $counter.text(count);
        
        // Анимация счетчика
        $counter.css('transform', 'scale(1.2)')
               .animate({ transform: 'scale(1)' }, 300);
    }
}

// Показ сообщения когда нет заявок
function showNoLeadsMessage() {
    const $container = $('.leads-container, .crm-table, .leads-list').first();
    if ($container.length > 0) {
        $container.append(
            '<div class="no-leads-message" style="text-align: center; padding: 40px; color: #666; font-size: 16px; grid-column: 1 / -1;">' +
            '📝 Нет заявок' +
            '</div>'
        );
    }
}
// Добавляем стили для анимации удаления
function addLeadDeleteStyles() {
    if (!$('#crm-lead-delete-styles').length) {
        $('head').append(`
            <style id="crm-lead-delete-styles">
                .lead-row.deleting {
                    opacity: 0.5;
                    background-color: #ffe6e6;
                    border-left: 3px solid #ff4444;
                }
                .zayv_del:disabled {
                    opacity: 0.6;
                    cursor: not-allowed;
                }
                .no-leads-message {
                    background: #f9f9f9;
                    border-radius: 8px;
                    margin: 20px 0;
                }
            </style>
        `);
    }
}

// Инициализация при загрузке
$(document).ready(function() {
    addLeadDeleteStyles();
    console.log('CRM Delete Lead functions initialized');
});