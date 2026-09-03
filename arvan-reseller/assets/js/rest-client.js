(function () {
	'use strict';

	class ArvanRestError extends Error {
		constructor(message, options) {
			super(message);
			this.name = 'ArvanRestError';
			this.code = options.code || 'arvan_reseller_unknown_error';
			this.status = Number(options.status || 0);
			this.retryAfter = Number(options.retryAfter || 0);
			this.kind = options.kind || 'backend';
			this.details = options.details || null;
		}
	}

	class ArvanRestClient {
		constructor(runtime) {
			this.runtime = runtime || {};
			this.root = String(this.runtime.restRoot || '');
			this.nonce = String(this.runtime.nonce || '');
			this.timeout = Number(this.runtime.timeoutMs || 20000);
			this.operationKeys = new Map();
		}

		operationKey(operation) {
			if (!this.operationKeys.has(operation)) {
				this.operationKeys.set(operation, this.uuid());
			}
			return this.operationKeys.get(operation);
		}

		completeOperation(operation) {
			this.operationKeys.delete(operation);
		}

		get(path, options) {
			return this.request(path, Object.assign({}, options, { method: 'GET' }));
		}

		getCollection(path, options) {
			return this.request(path, Object.assign({}, options, { method: 'GET', collection: true }));
		}

		post(path, body, options) {
			return this.request(path, Object.assign({}, options, { method: 'POST', body: body || {} }));
		}

		patch(path, body, options) {
			return this.request(path, Object.assign({}, options, { method: 'PATCH', body: body || {} }));
		}

		async request(path, options) {
			const settings = Object.assign({ method: 'GET', query: null, retries: null }, options || {});
			const method = String(settings.method).toUpperCase();
			const safeRetry = method === 'GET' || Boolean(settings.safeRetry && settings.idempotencyKey);
			const retries = settings.retries === null ? (safeRetry ? 2 : 0) : Number(settings.retries);
			let attempt = 0;
			let lastError;

			while (attempt <= retries) {
				try {
					return await this.fetchOnce(path, settings);
				} catch (error) {
					lastError = this.normalizeError(error);
					if (!safeRetry || attempt >= retries || !this.isRetryable(lastError)) {
						throw lastError;
					}
					const delay = lastError.retryAfter > 0 ? Math.min(lastError.retryAfter * 1000, 8000) : Math.min(650 * Math.pow(2, attempt), 3000);
					await new Promise((resolve) => window.setTimeout(resolve, delay));
					attempt += 1;
				}
			}

			throw lastError;
		}

		async fetchOnce(path, options) {
			if (!this.root) {
				throw new ArvanRestError('REST root is unavailable.', { code: 'arvan_reseller_backend_unavailable', kind: 'configuration' });
			}
			const url = new URL(String(path).replace(/^\/+/, ''), this.root);
			if (options.query) {
				Object.keys(options.query).forEach((key) => {
					const value = options.query[key];
					if (value !== '' && value !== null && typeof value !== 'undefined') {
						url.searchParams.set(key, String(value));
					}
				});
			}

			const controller = new AbortController();
			const timeoutId = window.setTimeout(() => controller.abort(), Number(options.timeout || this.timeout));
			const headers = { Accept: 'application/json', 'X-WP-Nonce': this.nonce };
			if (options.body !== undefined) {
				headers['Content-Type'] = 'application/json';
			}
			if (options.idempotencyKey) {
				headers['Idempotency-Key'] = String(options.idempotencyKey);
			}

			let response;
			try {
				response = await window.fetch(url.toString(), {
					method: options.method,
					headers: headers,
					credentials: 'same-origin',
					body: options.body === undefined ? undefined : JSON.stringify(options.body),
					signal: controller.signal
				});
			} catch (error) {
				if (error && error.name === 'AbortError') {
					throw new ArvanRestError('Request timed out.', { code: 'arvan_reseller_timeout', kind: 'timeout' });
				}
				throw new ArvanRestError('Network request failed.', { code: 'arvan_reseller_network_failure', kind: 'network' });
			} finally {
				window.clearTimeout(timeoutId);
			}

			const retryAfter = Number(response.headers.get('Retry-After') || 0);
			let payload = null;
			try {
				payload = await response.json();
			} catch (error) {
				if (!response.ok) {
					throw new ArvanRestError('Backend returned an unreadable response.', {
						code: 'arvan_reseller_backend_unavailable', status: response.status, retryAfter: retryAfter, kind: 'backend'
					});
				}
			}

			if (!response.ok || (payload && payload.code && payload.message && payload.data)) {
				const status = payload && payload.data && payload.data.status ? payload.data.status : response.status;
				const normalized = new ArvanRestError(payload && payload.message ? payload.message : 'Request failed.', {
					code: payload && payload.code ? payload.code : 'arvan_reseller_request_failed',
					status: status,
					retryAfter: retryAfter,
					kind: status === 401 ? 'session' : (status === 403 ? 'permission' : 'backend'),
					details: payload && payload.data ? payload.data : null
				});
				if (Number(status) === 401) {
					window.dispatchEvent(new CustomEvent('arvan:session-expired', { detail: normalized }));
				}
				throw normalized;
			}

			if (options.collection) {
				const page = Number(response.headers.get('X-Arvan-Page') || 1);
				const perPage = Number(response.headers.get('X-Arvan-Per-Page') || 0);
				return {
					items: Array.isArray(payload) ? payload : [],
					page: Number.isInteger(page) && page > 0 ? page : 1,
					perPage: Number.isInteger(perPage) && perPage > 0 ? perPage : (Array.isArray(payload) ? payload.length : 0),
					hasMore: String(response.headers.get('X-Arvan-Has-More') || '').toLowerCase() === 'true'
				};
			}

			return payload;
		}

		normalizeError(error) {
			if (error instanceof ArvanRestError) {
				return error;
			}
			return new ArvanRestError('Unexpected client error.', { code: 'arvan_reseller_client_error', kind: 'client' });
		}

		isRetryable(error) {
			return error.kind === 'network' || error.kind === 'timeout' || error.status === 429 || error.status >= 500;
		}

		uuid() {
			if (window.crypto && typeof window.crypto.randomUUID === 'function') {
				return window.crypto.randomUUID();
			}
			const values = new Uint8Array(16);
			window.crypto.getRandomValues(values);
			values[6] = (values[6] & 15) | 64;
			values[8] = (values[8] & 63) | 128;
			return Array.from(values).map((value, index) => ([4, 6, 8, 10].includes(index) ? '-' : '') + value.toString(16).padStart(2, '0')).join('');
		}
	}

	window.ArvanRestError = ArvanRestError;
	window.ArvanRestClient = ArvanRestClient;
	window.ArvanResellerAPI = new ArvanRestClient(window.ArvanResellerRuntime || {});
}());
