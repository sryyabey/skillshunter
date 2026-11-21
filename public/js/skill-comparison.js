const tray = document.querySelector('#compare-tray');
const page = document.querySelector('#skill-comparison');
const feedback = document.querySelector('#compare-feedback');
const storageKey = 'skillshunter.compare.v1';
let selected = [];
let focused = null;
let feedbackTimer;

function normalize(value) {
    if (!Array.isArray(value)) return [];
    return value.filter((item, index, all) => item && typeof item.slug === 'string'
        && /^[a-zA-Z0-9_-]{1,255}$/.test(item.slug) && typeof item.title === 'string'
        && item.title.length <= 255 && all.findIndex(other => other?.slug === item.slug) === index).slice(0, 3);
}
try { selected = normalize(JSON.parse(localStorage.getItem(storageKey))); } catch { /* Browser storage may be unavailable. */ }
if (page && new URLSearchParams(location.search).has('skills[]')) {
    selected = normalize(JSON.parse(page.dataset.selection));
}

function announce(message) {
    clearTimeout(feedbackTimer);
    feedback.textContent = message;
    feedback.classList.remove('sr-only');
    feedbackTimer = setTimeout(() => feedback.classList.add('sr-only'), 5000);
}
function comparisonUrl() {
    const url = new URL(tray.dataset.url);
    selected.forEach(item => url.searchParams.append('skills[]', item.slug));
    return url;
}
function persist() {
    try { localStorage.setItem(storageKey, JSON.stringify(selected)); }
    catch { announce('Browser storage is unavailable. Selections will last for this page only.'); }
}
function syncButtons() {
    document.querySelectorAll('[data-compare-toggle]').forEach(button => {
        const active = selected.some(item => item.slug === button.dataset.slug);
        button.setAttribute('aria-pressed', String(active));
        button.textContent = active ? 'Selected for comparison' : 'Compare';
    });
}
function render() {
    tray.hidden = selected.length === 0;
    document.body.classList.toggle('has-compare-tray', selected.length > 0);
    tray.querySelector('[data-compare-count]').textContent = selected.length;
    const items = tray.querySelector('[data-compare-items]');
    items.replaceChildren(...selected.map(item => {
        const chip = document.createElement('div');
        chip.className = 'compare-chip';
        const title = document.createElement('span');
        title.textContent = item.title;
        const remove = document.createElement('button');
        remove.type = 'button';
        remove.dataset.compareRemove = item.slug;
        remove.setAttribute('aria-label', `Remove ${item.title}`);
        remove.textContent = '×';
        chip.append(title, remove);
        return chip;
    }));
    tray.querySelector('[data-compare-open]').href = comparisonUrl().href;
    syncButtons();
}
function updatePage() {
    if (page) location.assign(comparisonUrl());
}
document.addEventListener('click', event => {
    const button = event.target.closest('button');
    if (!button) return;
    if (button.matches('[data-compare-toggle]')) {
        const slug = button.dataset.slug;
        if (selected.some(item => item.slug === slug)) {
            selected = selected.filter(item => item.slug !== slug);
        } else {
            if (selected.length >= 3) { announce('You can compare up to 3 skills. Remove one first.'); return; }
            selected = normalize([...selected, { slug, title: button.dataset.title }]);
        }
        persist(); render(); announce(`${selected.length} of 3 skills selected.`);
    }
    if (button.matches('[data-compare-remove]')) {
        selected = selected.filter(item => item.slug !== button.dataset.compareRemove);
        persist(); render(); updatePage();
        if (!page) announce('Skill removed from comparison.');
    }
    if (button.matches('[data-compare-clear]')) {
        selected = []; persist(); render(); updatePage();
        if (!page) announce('Comparison cleared.');
    }
    if (button.matches('[data-compare-focus]')) {
        const index = Number(button.dataset.compareFocus);
        focused = focused === index ? null : index;
        const table = page.querySelector('table');
        table.classList.toggle('has-focus', focused !== null);
        const columns = table.querySelectorAll('[data-compare-col]');
        columns.forEach((column, i) => {
            const share = focused === null ? 1 / columns.length : i === focused ? .55 : .45 / (columns.length - 1);
            column.style.width = `calc((100% - 150px) * ${share})`;
        });
        table.querySelectorAll('[data-column]').forEach(cell => cell.classList.toggle('is-focused', Number(cell.dataset.column) === focused));
        table.querySelectorAll('[data-compare-focus]').forEach(control => control.setAttribute('aria-pressed', String(Number(control.dataset.compareFocus) === focused)));
        page.querySelector('[data-comparison-status]').textContent = focused === null ? 'Equal column view restored.' : `${button.textContent.trim()} expanded. Click the title again to restore.`;
        if (focused !== null) {
            page.querySelector('.comparison-scroll').scrollTo({ left: Math.max(0, button.closest('th').offsetLeft - 150), behavior: matchMedia('(prefers-reduced-motion: reduce)').matches ? 'instant' : 'smooth' });
        }
    }
    if (button.matches('[data-compare-share]')) {
        const url = new URL(location.href);
        url.hash = '';
        navigator.clipboard?.writeText(url.href).then(() => announce('Comparison link copied.')).catch(() => announce('Copy the comparison URL from your address bar.'));
        if (!navigator.clipboard) announce('Copy the comparison URL from your address bar.');
    }
});
page?.querySelector('[data-compare-differences]')?.addEventListener('change', event => {
    page.querySelectorAll('tr[data-different=false]').forEach(row => { row.hidden = event.target.checked; });
    page.querySelector('[data-comparison-status]').textContent = event.target.checked ? 'Showing differences only.' : 'Showing all features.';
});
const results = document.querySelector('#catalog-results');
if (results) new MutationObserver(syncButtons).observe(results, { childList: true });
window.addEventListener('storage', event => {
    if (event.key !== storageKey && event.key !== null) return;
    try { selected = normalize(JSON.parse(event.newValue)); } catch { selected = []; }
    render();
});
window.addEventListener('pageshow', event => {
    if (!event.persisted || page) return;
    try { selected = normalize(JSON.parse(localStorage.getItem(storageKey))); } catch { /* Keep in-memory selections. */ }
    render();
});
new ResizeObserver(() => document.body.style.setProperty('--compare-tray-height', `${tray.hidden ? 0 : tray.getBoundingClientRect().height}px`)).observe(tray);
render();
if (page && new URLSearchParams(location.search).has('skills[]')) persist();
