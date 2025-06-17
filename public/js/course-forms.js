// Управление формами курсов (создание и редактирование)
document.addEventListener('DOMContentLoaded', function() {
    console.log('Course forms JS loaded'); // Отладка
    
    // Попробуем найти элементы по разным возможным ID
    let courseTypeSelect = document.getElementById('course_type');
    if (!courseTypeSelect) {
        courseTypeSelect = document.getElementById('course_courseType');
    }
    if (!courseTypeSelect) {
        courseTypeSelect = document.querySelector('select[name*="courseType"]');
    }
    
    const priceField = document.getElementById('price_field');
    
    console.log('courseTypeSelect:', courseTypeSelect); // Отладка
    console.log('priceField:', priceField); // Отладка
    
    if (!courseTypeSelect || !priceField) {
        console.log('Elements not found!'); // Отладка
        // Выведем все элементы формы для отладки
        const allSelects = document.querySelectorAll('select');
        console.log('All selects:', allSelects);
        const allInputs = document.querySelectorAll('input');
        console.log('All inputs:', allInputs);
        return; // Если элементы не найдены, выходим
    }
    
    const priceInput = priceField.querySelector('input');
    console.log('priceInput:', priceInput); // Отладка
    
    function togglePriceField() {
        const selectedType = courseTypeSelect.value;
        console.log('Selected type:', selectedType); // Отладка
        
        if (selectedType === 'free') {
            // Скрываем поле стоимости для бесплатных курсов
            console.log('Hiding price field'); // Отладка
            priceField.style.display = 'none';
            if (priceInput) {
                priceInput.required = false;
                priceInput.value = '0'; // Устанавливаем 0 для бесплатных курсов
            }
        } else {
            // Показываем поле стоимости для платных курсов
            console.log('Showing price field'); // Отладка
            priceField.style.display = 'block';
            if (priceInput) {
                priceInput.required = true;
                if (priceInput.value === '0') {
                    priceInput.value = ''; // Очищаем поле для ввода цены
                }
            }
        }
    }
    
    // Слушаем изменения типа курса
    courseTypeSelect.addEventListener('change', function() {
        console.log('Type changed!'); // Отладка
        togglePriceField();
    });
    
    // Инициализация при загрузке страницы
    console.log('Initializing...'); // Отладка
    togglePriceField();
}); 