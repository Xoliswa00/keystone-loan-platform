/**
 * Global duplicate-submit guard. Disables the submitting button and swaps in
 * a spinner the instant any <form> is submitted, site-wide — no per-form
 * markup needed. Opt out on a specific form with data-no-loader.
 *
 * Skips forms whose submit was already prevented by another handler (e.g.
 * two-factor.blade.php's @submit.prevent guard that only calls $el.submit()
 * once a 6-digit code is entered) — disabling on a submit that didn't
 * actually go anywhere would leave the button stuck.
 */
document.addEventListener('submit', (e) => {
    const form = e.target;
    if (!(form instanceof HTMLFormElement)) return;
    if (form.dataset.noLoader !== undefined) return;
    if (e.defaultPrevented) return;
    if (form.dataset.submitting === 'true') {
        e.preventDefault();
        return;
    }

    const submitter = e.submitter || form.querySelector('[type="submit"]');
    if (!submitter) return;

    form.dataset.submitting = 'true';
    submitter.dataset.originalHtml = submitter.innerHTML;
    submitter.disabled = true;
    submitter.classList.add('opacity-60', 'cursor-not-allowed');
    submitter.innerHTML = `
        <svg class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24">
            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
        </svg>
        Please wait…
    `;
});

// A back/forward-cache restore (browser Back button after a validation
// error, etc.) can bring back a page with buttons still mid-submit from the
// user's last visit — reset them so the form is usable again.
window.addEventListener('pageshow', () => {
    document.querySelectorAll('form[data-submitting="true"]').forEach((form) => {
        form.dataset.submitting = 'false';
    });
    document.querySelectorAll('[data-original-html]').forEach((submitter) => {
        submitter.disabled = false;
        submitter.classList.remove('opacity-60', 'cursor-not-allowed');
        submitter.innerHTML = submitter.dataset.originalHtml;
        delete submitter.dataset.originalHtml;
    });
});
