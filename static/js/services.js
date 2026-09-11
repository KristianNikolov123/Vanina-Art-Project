document.addEventListener('DOMContentLoaded', () => {
    const form = document.getElementById('servicesPricingForm');
    if (!form) {
        return;
    }

    form.addEventListener('submit', (event) => {
        const invalid = form.querySelector(':invalid');
        if (invalid) {
            invalid.reportValidity();
            event.preventDefault();
        }
    });
});
