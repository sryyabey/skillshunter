const form = document.querySelector('#catalog-search');
const results = document.querySelector('#catalog-results');
const status = document.querySelector('#catalog-search-status');
let displayedQuery = window.location.search;
let pendingRequest;

async function loadResults(url, { push = true, syncForm = true, focusResults = false } = {}) {
    pendingRequest?.abort();
    const request = new AbortController();
    pendingRequest = request;

    const endpoint = new URL(url);
    endpoint.searchParams.set('_catalog_results', '1');
    results.setAttribute('aria-busy', 'true');
    status.classList.add('sr-only');
    status.textContent = 'Searching skills…';

    try {
        const response = await fetch(endpoint, {
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
            signal: request.signal,
        });
        if (!response.ok) {
            throw new Error('Catalog search failed');
        }

        const template = document.createElement('template');
        template.innerHTML = await response.text();
        if (request.signal.aborted) {
            return;
        }

        const count = template.content.querySelector('#resultCount');
        if (!count) {
            throw new Error('Catalog results were missing');
        }

        if (push && url.href !== window.location.href) {
            history.pushState(null, '', url);
        }
        displayedQuery = url.search;
        results.replaceChildren(template.content);

        if (syncForm) {
            form.elements.q.value = url.searchParams.get('q') ?? '';
            form.elements.category.value = url.searchParams.get('category') ?? '';
        }
        if (focusResults) {
            results.querySelector('h2')?.focus({ preventScroll: true });
        }
        status.textContent = count.textContent.trim().toLowerCase();
    } catch {
        if (!request.signal.aborted) {
            status.classList.remove('sr-only');
            status.textContent = 'Search could not be updated. Please try again.';
        }
    } finally {
        if (pendingRequest === request) {
            pendingRequest = undefined;
            results.removeAttribute('aria-busy');
        }
    }
}

form.addEventListener('submit', (event) => {
    event.preventDefault();
    const url = new URL(form.action);
    for (const [name, value] of new FormData(form)) {
        if (value.trim()) {
            url.searchParams.set(name, value.trim());
        }
    }
    loadResults(url, { syncForm: false });
});

document.addEventListener('click', (event) => {
    const link = event.target.closest('a[data-catalog-link], [data-catalog-pagination] a');
    if (!link || event.defaultPrevented || event.button !== 0 || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) {
        return;
    }

    event.preventDefault();
    loadResults(new URL(link.href), { focusResults: results.contains(link) });
});

window.addEventListener('popstate', () => {
    const url = new URL(window.location.href);
    if (url.search !== displayedQuery) {
        loadResults(url, { push: false });
    } else if (pendingRequest) {
        pendingRequest.abort();
        status.textContent = '';
    }
});
