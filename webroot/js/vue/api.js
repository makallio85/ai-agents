/**
 * Shared base API layer for AI-Agents platform.
 * All Vue pages load this file first.
 */
var Api = (function () {
    'use strict';

    var webroot = window.webroot || '/';

    function _toSlug(name) {
        return name
            .replace(/([A-Z]+)([A-Z][a-z])/g, '$1-$2')
            .replace(/([a-z\d])([A-Z])/g, '$1-$2')
            .toLowerCase();
    }

    function _request(method, url, body, signal) {
        var options = {
            method: method,
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
            credentials: 'same-origin',
            signal: signal,
        };

        if (body && method !== 'GET' && method !== 'HEAD') {
            options.body = JSON.stringify(body);
        }

        return fetch(url, options).then(function (response) {
            if (!response.ok && response.status === 401) {
                window.location.href = '/login';
                return Promise.reject(new Error('Unauthenticated'));
            }
            return response.json().then(function (data) {
                if (!data.success) {
                    var err = new Error(data.errors && data.errors[0] || 'Request failed');
                    err.errors = data.errors || [];
                    err.status = response.status;
                    return Promise.reject(err);
                }
                return data;
            });
        });
    }

    function createNamespace(plugin, controller) {
        var pluginSlug = _toSlug(plugin);
        var controllerSlug = _toSlug(controller);
        var base = webroot + 'api/v1/' + pluginSlug + '/' + controllerSlug;

        return {
            get: function (action, params) {
                var url = base;
                if (action && action !== 'index') {
                    url += '/' + action;
                }
                if (params && params.id) {
                    url += '/' + params.id;
                }
                if (params) {
                    var qs = Object.keys(params)
                        .filter(function (k) { return k !== 'id'; })
                        .map(function (k) { return encodeURIComponent(k) + '=' + encodeURIComponent(params[k]); })
                        .join('&');
                    if (qs) url += '?' + qs;
                }
                return _request('GET', url);
            },
            post: function (action, body) {
                var url = base + '/' + action;
                return _request('POST', url, body);
            },
            put: function (action, id, body) {
                var url = base + '/' + action + '/' + id;
                return _request('PUT', url, body);
            },
            del: function (action, id) {
                var url = base + '/' + action + '/' + id;
                return _request('DELETE', url);
            },
        };
    }

    // Core API namespace (non-plugin endpoints)
    function _coreRequest(method, path, body) {
        var url = webroot + 'api/v1/' + path;
        return _request(method, url, body);
    }

    var auth = {
        login: function (email, password) {
            return _coreRequest('POST', 'auth/login', { email: email, password: password });
        },
        verifyMfa: function (userId, token) {
            return _coreRequest('POST', 'auth/verify-mfa', { user_id: userId, token: token });
        },
        logout: function () {
            return _coreRequest('POST', 'auth/logout');
        },
        me: function () {
            return _coreRequest('GET', 'auth/me');
        },
    };

    var agents = {
        index: function () { return _coreRequest('GET', 'agents'); },
        view: function (id) { return _coreRequest('GET', 'agents/view/' + id); },
        create: function (data) { return _coreRequest('POST', 'agents/create', data); },
        update: function (id, data) { return _coreRequest('PUT', 'agents/update/' + id, data); },
        del: function (id) { return _coreRequest('DELETE', 'agents/delete/' + id); },
        logs: function (id) { return _coreRequest('GET', 'agents/logs/' + id); },
    };

    var conversations = {
        index: function () { return _coreRequest('GET', 'conversations'); },
        view: function (id) { return _coreRequest('GET', 'conversations/view/' + id); },
        create: function (data) { return _coreRequest('POST', 'conversations/create', data); },
        del: function (id) { return _coreRequest('DELETE', 'conversations/delete/' + id); },
    };

    var labels = {
        index: function () { return _coreRequest('GET', 'labels'); },
        view: function (id) { return _coreRequest('GET', 'labels/view/' + id); },
        create: function (data) { return _coreRequest('POST', 'labels/create', data); },
        update: function (id, data) { return _coreRequest('PUT', 'labels/update/' + id, data); },
        del: function (id) { return _coreRequest('DELETE', 'labels/delete/' + id); },
    };

    var logs = {
        index: function (params) {
            var qs = '';
            if (params) {
                var parts = Object.keys(params)
                    .filter(function (k) { return params[k] !== '' && params[k] != null; })
                    .map(function (k) { return encodeURIComponent(k) + '=' + encodeURIComponent(params[k]); });
                if (parts.length) { qs = '?' + parts.join('&'); }
            }
            return _coreRequest('GET', 'logs' + qs);
        },
    };

    var githubIntegrations = {
        index: function () { return _coreRequest('GET', 'github-integrations'); },
        view: function (id) { return _coreRequest('GET', 'github-integrations/view/' + id); },
        create: function (data) { return _coreRequest('POST', 'github-integrations/create', data); },
        update: function (id, data) { return _coreRequest('PUT', 'github-integrations/update/' + id, data); },
        del: function (id) { return _coreRequest('DELETE', 'github-integrations/delete/' + id); },
    };

    return {
        _toSlug: _toSlug,
        createNamespace: createNamespace,
        auth: auth,
        agents: agents,
        conversations: conversations,
        labels: labels,
        logs: logs,
        githubIntegrations: githubIntegrations,
    };
})();
