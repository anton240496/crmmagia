
        document.addEventListener('DOMContentLoaded', function() {
            const accordions = document.querySelectorAll('.ihey_spisok');
            
            accordions.forEach(accordion => {
                const header = accordion.querySelector('.ihey_spisok_name');
                
                header.addEventListener('click', function() {
                    // Закрываем все остальные аккордеоны
                    accordions.forEach(item => {
                        if (item !== accordion) {
                            item.classList.remove('active');
                        }
                    });
                    
                    // Переключаем текущий аккордеон
                    accordion.classList.toggle('active');
                });
            });
        });