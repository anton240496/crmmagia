jQuery(document).ready(function($) {
    let checkInterval;
    
    // Функция для обновления счетчика
    function updateCounter() {
        $.ajax({
            url: '/wp-admin/admin-ajax.php',
            type: 'POST',
            data: {
                action: 'get_new_leads_count'
            },
            success: function(response) {
                if (response.success && response.data.count > 0) {
                    $('.header__zayv span').text(response.data.count);
                    $('.header__zayv').show();
                } else {
                    $('.header__zayv span').text('0');
                }
            }
        });
    }
    
    // Проверяем каждые 30 секунд
    function startChecking() {
        checkInterval = setInterval(updateCounter, 30000);
    }
    
    // Обработчик клика по кнопке
    $('.header__zayv').on('click', function(e) {
        e.preventDefault();
        
        // Отмечаем заявки как просмотренные
        $.ajax({
            url: '/wp-admin/admin-ajax.php',
            type: 'POST',
            data: {
                action: 'mark_leads_viewed'
            },
            success: function() {
                // Обновляем счетчик сразу
                $('.header__zayv span').text('0');
                
                // Перезагружаем страницу через 500мс
                setTimeout(function() {
                    location.reload();
                }, 500);
            }
        });
    });
    
    // Запускаем проверку при загрузке страницы
    updateCounter();
    startChecking();
    
    // Также обновляем счетчик при фокусе на окне (когда пользователь возвращается к вкладке)
    $(window).on('focus', function() {
        updateCounter();
    });
});