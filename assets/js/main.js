
document.addEventListener('DOMContentLoaded', function() {
    const menuToggle = document.querySelector('.menu-toggle');
    const navbarContainer = document.querySelector('.navbar-container');
    
    if (menuToggle) {
        menuToggle.addEventListener('click', function() {
            navbarContainer.classList.toggle('nav-active');
        });
    }
    

    document.addEventListener('click', function(event) {
        if (navbarContainer && navbarContainer.classList.contains('nav-active') && 
            !event.target.closest('.navbar-container') && 
            !event.target.closest('.menu-toggle')) {
            navbarContainer.classList.remove('nav-active');
        }
    });
    
    
    const contactForm = document.querySelector('.contact-form form');
    if (contactForm) {
        contactForm.addEventListener('submit', function(event) {
            let valid = true;
            const nameInput = document.getElementById('name');
            const emailInput = document.getElementById('email');
            const messageInput = document.getElementById('message');
            
            
            if (nameInput && nameInput.value.trim() === '') {
                valid = false;
                showError(nameInput, 'Name is required');
            }
            
            if (emailInput) {
                const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
                if (!emailRegex.test(emailInput.value)) {
                    valid = false;
                    showError(emailInput, 'Valid email is required');
                }
            }
            
            if (messageInput && messageInput.value.trim() === '') {
                valid = false;
                showError(messageInput, 'Message is required');
            }
            
            if (!valid) {
                event.preventDefault();
            }
        });
    }
    
    function showError(inputElement, message) {
        
        const parent = inputElement.parentElement;
        const existingError = parent.querySelector('.error-message');
        if (existingError) {
            parent.removeChild(existingError);
        }
        
        
        inputElement.classList.add('error');
        
        
        const errorMessage = document.createElement('div');
        errorMessage.className = 'error-message';
        errorMessage.textContent = message;
        parent.appendChild(errorMessage);
        
        
        inputElement.addEventListener('input', function() {
            inputElement.classList.remove('error');
            const error = parent.querySelector('.error-message');
            if (error) {
                parent.removeChild(error);
            }
        }, { once: true });
    }
    
    
    const availabilityMeter = document.querySelector('.meter-fill');
    if (availabilityMeter) {
        
        
        setInterval(function() {
            const availabilityText = document.querySelector('.availability-box p strong');
            if (availabilityText) {
                const parts = availabilityText.textContent.split('/');
                if (parts.length === 2) {
                    const available = parseInt(parts[0]);
                    const total = parseInt(parts[1]);
                    
                    
                    let newAvailable = available + (Math.random() > 0.5 ? 1 : -1);
                    
                    newAvailable = Math.max(0, Math.min(total, newAvailable));
                    
                    const percentAvailable = (newAvailable / total) * 100;
                    availabilityMeter.style.width = percentAvailable + '%';
                    availabilityText.textContent = newAvailable + '/' + total;
                }
            }
        }, 5000);
    }
});


function calculateCost() {
    const spaceSelect = document.getElementById('space-id');
    const hoursInput = document.getElementById('hours');
    const costDisplay = document.getElementById('cost-display');
    
    if (spaceSelect && hoursInput && costDisplay) {
        const spaceOption = spaceSelect.options[spaceSelect.selectedIndex];
        const rate = parseFloat(spaceOption.getAttribute('data-rate') || 0);
        const hours = parseInt(hoursInput.value) || 0;
        
        const totalCost = rate * hours;
        costDisplay.textContent = '$' + totalCost.toFixed(2);
    }
}


function confirmDelete(id, type) {
    if (confirm(`Are you sure you want to delete this ${type}? This action cannot be undone.`)) {
        document.getElementById(`delete-${type}-${id}`).submit();
    }
}

document.addEventListener('DOMContentLoaded', function() {
    
    const contactLinks = document.querySelectorAll('a[href="#contact"]');
    
    contactLinks.forEach(link => {
        link.addEventListener('click', function(e) {
            e.preventDefault();
            
            const contactSection = document.getElementById('contact');
            if (contactSection) {
                contactSection.scrollIntoView({ 
                    behavior: 'smooth',
                    block: 'start'
                });
            }
        });
    });
});

document.addEventListener('DOMContentLoaded', function() {
    const contactForm = document.querySelector('.contact-form form');
    if (contactForm) {
        contactForm.addEventListener('submit', function(event) {
            let valid = true;
            const nameInput = document.getElementById('name');
            const emailInput = document.getElementById('email');
            const messageInput = document.getElementById('message');
            
            
            if (nameInput && nameInput.value.trim() === '') {
                valid = false;
                showError(nameInput, 'Name is required');
            }
            
            if (emailInput) {
                const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
                if (!emailRegex.test(emailInput.value)) {
                    valid = false;
                    showError(emailInput, 'Valid email is required');
                }
            }
            
            if (messageInput && messageInput.value.trim() === '') {
                valid = false;
                showError(messageInput, 'Message is required');
            }
            
            if (!valid) {
                event.preventDefault();
            }
        });
    }
    
    function showError(inputElement, message) {
        
        const parent = inputElement.parentElement;
        const existingError = parent.querySelector('.error-message');
        if (existingError) {
            parent.removeChild(existingError);
        }
        
        
        inputElement.classList.add('error');
        
        
        const errorMessage = document.createElement('div');
        errorMessage.className = 'error-message';
        errorMessage.textContent = message;
        parent.appendChild(errorMessage);
        
        
        inputElement.addEventListener('input', function() {
            inputElement.classList.remove('error');
            const error = parent.querySelector('.error-message');
            if (error) {
                parent.removeChild(error);
            }
        }, { once: true });
    }
});

document.addEventListener('DOMContentLoaded', function() {
    function highlightNavOnScroll() {
        const sections = document.querySelectorAll('section[id]');
        const navLinks = document.querySelectorAll('.nav-links a');
        
        
        const scrollY = window.pageYOffset;
        
        
        sections.forEach(section => {
            const sectionHeight = section.offsetHeight;
            const sectionTop = section.offsetTop - 100;
            const sectionId = section.getAttribute('id');
            
            if (scrollY > sectionTop && scrollY <= sectionTop + sectionHeight) {
                // Find the corresponding navigation link
                navLinks.forEach(link => {
                    link.classList.remove('active');
                    if (link.getAttribute('href') === '#' + sectionId) {
                        link.classList.add('active');
                    }
                });
            }
        });
    }
    
    
    window.addEventListener('scroll', highlightNavOnScroll);
});

