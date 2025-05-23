// Game timer functionality
document.addEventListener('DOMContentLoaded', function() {
    const timerElement = document.getElementById('gameTimer');
    if (timerElement) {
        let timeLeft = parseInt(timerElement.dataset.timeLimit) || 3600; // Default: 60 minutes
        let startTime = parseInt(timerElement.dataset.startTime) || Math.floor(Date.now() / 1000);
        let elapsed = Math.floor(Date.now() / 1000) - startTime;
        timeLeft = Math.max(0, timeLeft - elapsed);
        
        updateTimer(timeLeft);

        const timerInterval = setInterval(function() {
            timeLeft -= 1;
            updateTimer(timeLeft);
            
            if (timeLeft <= 0) {
                clearInterval(timerInterval);
                document.getElementById('timeUpForm').submit();
            }
        }, 1000);

        function updateTimer(seconds) {
            const minutes = Math.floor(seconds / 60);
            const remainingSeconds = seconds % 60;
            timerElement.textContent = `${minutes.toString().padStart(2, '0')}:${remainingSeconds.toString().padStart(2, '0')}`;
            
            // Change color when time is running out
            if (seconds < 300) { // Less than 5 minutes
                timerElement.classList.add('time-critical');
            }
        }
    }
    
    // Show hint functionality
    const hintButtons = document.querySelectorAll('.show-hint');
    hintButtons.forEach(button => {
        button.addEventListener('click', function(e) {
            e.preventDefault();
            const hintId = this.getAttribute('data-hint');
            const hintElement = document.getElementById(hintId);
            
            if (hintElement.style.display === 'none' || !hintElement.style.display) {
                hintElement.style.display = 'block';
                this.textContent = 'Hide Hint';
            } else {
                hintElement.style.display = 'none';
                this.textContent = 'Show Hint';
            }
        });
    });
    
    // Form validation
    const forms = document.querySelectorAll('form.needs-validation');
    forms.forEach(form => {
        form.addEventListener('submit', function(event) {
            if (!form.checkValidity()) {
                event.preventDefault();
                event.stopPropagation();
            }
            
            form.classList.add('was-validated');
        });
    });
});
