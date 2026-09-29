(function setupEmailVerification() {
    const verificationForm = document.getElementById('verification-form');

    if (!verificationForm) {
        return;
    }

    const codeInput = document.getElementById('code');
    const digitGroup = verificationForm.querySelector('.verification-digits');
    const digitInputs = Array.from(digitGroup.querySelectorAll('input'));
    const verifyButton = document.getElementById('verification-submit');
    const resendButton = document.getElementById('resend-code');
    const resendForm = document.getElementById('resend-form');
    const timerLabel = document.getElementById('verification-timer');

    const pageStartTime = performance.now();
    const initialCodeSeconds = Number(verificationForm.dataset.remaining);
    const initialResendSeconds = Number(verificationForm.dataset.resend);
    const attemptsLocked = verificationForm.dataset.locked === '1';
    let isSubmitting = false;

    
    // Use six boxes for typing and a hidden field to send the full code
    codeInput.type = 'hidden';
    codeInput.required = false;
    digitGroup.hidden = false;
    verificationForm.querySelector('label').htmlFor = 'verification-digit-1';

    for (let index = 0; index < digitInputs.length; index++) {
        digitInputs[index].id = 'verification-digit-' + (index + 1);
    }

    // Convert digits to English and remove anything that is not a number
    function cleanCode(value) {
        const arabicDigits = '٠١٢٣٤٥٦٧٨٩';
        const persianDigits = '۰۱۲۳۴۵۶۷۸۹';
        let cleanedCode = '';

        for (const character of value) {
            if (character >= '0' && character <= '9') {
                cleanedCode += character;
            } else if (arabicDigits.includes(character)) {
                cleanedCode += arabicDigits.indexOf(character);
            } else if (persianDigits.includes(character)) {
                cleanedCode += persianDigits.indexOf(character);
            }
        }

        return cleanedCode;
    }

    function getElapsedSeconds() {
        return Math.floor((performance.now() - pageStartTime) / 1000);
    }

    function getCodeSecondsLeft() {
        return Math.max(0, initialCodeSeconds - getElapsedSeconds());
    }

    function updateCodeAndButton() {
        let completeCode = '';

        for (const input of digitInputs) {
            completeCode += input.value;

            if (input.value !== '') {
                input.classList.add('has-value');
            } else {
                input.classList.remove('has-value');
            }
        }

        codeInput.value = completeCode;

        const codeIsIncomplete = completeCode.length !== 6;
        const codeHasExpired = getCodeSecondsLeft() === 0;

        if (isSubmitting || codeIsIncomplete || codeHasExpired || attemptsLocked) {
            verifyButton.disabled = true;
        } else {
            verifyButton.disabled = false;
        }
    }

    function fillCode(value, currentIndex) {
        const cleanedCode = cleanCode(value);

        if (cleanedCode === '') {
            digitInputs[currentIndex].value = '';
            updateCodeAndButton();
            return;
        }

        
        let startIndex = currentIndex;
        // Fill from the first box when the full code is pasted
        if (cleanedCode.length >= 6) {
            startIndex = 0;
        }

        const charactersToFill = cleanedCode.slice(0, 6 - startIndex);

        for (let index = 0; index < charactersToFill.length; index++) {
            digitInputs[startIndex + index].value = charactersToFill[index];
        }

        const nextIndex = Math.min(startIndex + cleanedCode.length, 5);
        digitInputs[nextIndex].focus();

        for (const input of digitInputs) {
            input.removeAttribute('aria-invalid');
        }

        updateCodeAndButton();
    }

    digitInputs.forEach(function (input, index) {
        input.addEventListener('focus', function () {
            input.select();
        });

        input.addEventListener('input', function () {
            fillCode(input.value, index);
        });

        input.addEventListener('paste', function (event) {
            event.preventDefault();
            const pastedText = event.clipboardData.getData('text');
            fillCode(pastedText, index);
        });

        input.addEventListener('keydown', function (event) {
            if (event.key === 'Backspace' && input.value === '' && index > 0) {
                event.preventDefault();
                digitInputs[index - 1].value = '';
                digitInputs[index - 1].focus();
                updateCodeAndButton();
            } else if (event.key === 'ArrowLeft' && index > 0) {
                event.preventDefault();
                digitInputs[index - 1].focus();
            } else if (event.key === 'ArrowRight' && index < 5) {
                event.preventDefault();
                digitInputs[index + 1].focus();
            }
        });
    });

    function updateTimers() {
        const codeSecondsLeft = getCodeSecondsLeft();
        const resendSecondsLeft = Math.max(0, initialResendSeconds - getElapsedSeconds());

        if (attemptsLocked) {
            timerLabel.textContent = 'Too many attempts. Request a new code.';
        } else if (codeSecondsLeft === 0) {
            timerLabel.textContent = 'Request a new code to continue.';
        } else {
            timerLabel.textContent = 'Code expires in ';

            const minutes = Math.floor(codeSecondsLeft / 60);
            const seconds = String(codeSecondsLeft % 60).padStart(2, '0');
            const timeDisplay = document.createElement('strong');
            timeDisplay.textContent = minutes + ':' + seconds;
            timerLabel.append(timeDisplay);
        }

        if (resendSecondsLeft > 0) {
            resendButton.disabled = true;
            resendButton.textContent = 'Resend in ' + resendSecondsLeft + 's';
        } else {
            resendButton.disabled = false;
            resendButton.textContent = 'Resend code';
        }

        updateCodeAndButton();
    }

    verificationForm.addEventListener('submit', function (event) {
        updateCodeAndButton();

        if (verifyButton.disabled) {
            event.preventDefault();
            return;
        }

        isSubmitting = true;
        verifyButton.disabled = true;
        verifyButton.textContent = 'Verifying…';
    });

    resendForm.addEventListener('submit', function () {
        resendButton.disabled = true;
        resendButton.textContent = 'Sending…';
        clearInterval(timerInterval);
    });

    updateTimers();
    const timerInterval = setInterval(updateTimers, 1000);

    // Reset the button and timer when returning with the back button
    window.addEventListener('pageshow', function () {
        isSubmitting = false;
        verifyButton.textContent = 'Verify email';
        updateTimers();
    });

    digitInputs[0].focus();
})();