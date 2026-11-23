'use strict';

/**
 * Регистрация инструментов сайта для браузерных AI-агентов через WebMCP
 * (document.modelContext). Progressive enhancement: если API нет —
 * тихий выход, на работу сайта это не влияет.
 *
 * Инструменты и их схемы собираются сервером из реестра модуля
 * alexw.clientsmcp (только помеченные browser_safe) и исполняются
 * в контексте сессии посетителя.
 */
(function () {
    var PATH = '/alexw-clientsmcp-webmcp.json';

    if (!document.modelContext || !document.modelContext.registerTool) {
        return;
    }

    var sessid = '';

    function getCatalog() {
        return fetch(PATH, { credentials: 'same-origin' })
            .then(function (response) {
                if (!response.ok) {
                    throw new Error('WebMCP catalog: HTTP ' + response.status);
                }
                return response.json();
            });
    }

    function executeTool(name, args) {
        return fetch(PATH, {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ sessid: sessid, name: name, arguments: args || {} })
        })
            .then(function (response) { return response.json(); })
            .then(function (data) {
                if (!data.ok) {
                    throw new Error(data.error + (data.detail ? ': ' + data.detail : ''));
                }
                var result = data.result;
                if (result && result.content && result.content[0] && typeof result.content[0].text === 'string') {
                    try {
                        return JSON.parse(result.content[0].text);
                    } catch (e) {
                        return result.content[0].text;
                    }
                }
                return result;
            });
    }

    function registerAll(catalog) {
        sessid = catalog.sessid || '';

        (catalog.tools || []).forEach(function (tool) {
            var registration = {
                name: tool.name,
                description: tool.description || tool.name,
                inputSchema: tool.inputSchema || { type: 'object', properties: {} },
                execute: function (args) {
                    return executeTool(tool.name, args);
                }
            };

            document.modelContext.registerTool(registration).catch(function (error) {
                console.error('WebMCP: не удалось зарегистрировать ' + tool.name, error);
            });
        });
    }

    getCatalog().then(registerAll).catch(function (error) {
        console.error('WebMCP: каталог инструментов недоступен', error);
    });
})();
