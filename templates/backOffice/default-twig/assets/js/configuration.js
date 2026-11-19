(() => {
    const form = document.getElementById('creditaccount-config-form');

    if (!form) {
        return;
    }

    const mode = form.querySelector('select[name$="[loyalty_mode]"]');
    const sections = form.querySelectorAll('[data-credit-mode]');

    const showTheSectionOfTheMode = () => {
        sections.forEach((section) => {
            section.hidden = section.dataset.creditMode !== mode.value;
        });
    };

    mode.addEventListener('change', showTheSectionOfTheMode);
    showTheSectionOfTheMode();

    const tiers = form.querySelector('[data-tiers]');
    const prototype = form.querySelector('[data-tier-prototype]');

    form.querySelector('[data-tier-add]').addEventListener('click', () => {
        const index = Number(tiers.dataset.nextIndex);
        tiers.dataset.nextIndex = String(index + 1);

        const row = document.createElement('tbody');
        row.innerHTML = prototype.innerHTML.replaceAll('__name__', String(index));
        tiers.append(...row.children);
    });

    form.addEventListener('click', (event) => {
        const remove = event.target.closest('[data-tier-remove]');

        if (remove) {
            remove.closest('[data-tier-row]').remove();
        }
    });
})();
