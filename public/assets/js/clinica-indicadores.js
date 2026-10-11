(function () {
    'use strict';

    var SVG_NS = 'http://www.w3.org/2000/svg';

    function numericValue(value) {
        var number = Number(value);
        return isFinite(number) && number >= 0 ? number : 0;
    }

    function maxValue(items, key) {
        return (items || []).reduce(function (max, item) {
            return Math.max(max, numericValue(item && item[key]));
        }, 0);
    }

    function scaleValue(value, max, plotHeight) {
        var safeMax = numericValue(max);
        if (safeMax === 0) {
            return 0;
        }
        return numericValue(value) / safeMax * plotHeight;
    }

    function barMetrics(items, key, plotHeight) {
        var max = maxValue(items, key) || 1;
        return {
            max: max,
            values: (items || []).map(function (item) {
                var value = numericValue(item && item[key]);
                var height = scaleValue(value, max, plotHeight);
                return {
                    value: value,
                    height: value > 0 ? Math.max(height, 1.5) : 0
                };
            })
        };
    }

    function hasPositiveValue(items, keys) {
        return (items || []).some(function (item) {
            return (keys || ['value']).some(function (key) {
                return numericValue(item && item[key]) > 0;
            });
        });
    }

    /* Exposed only as pure helpers so fixture tests can verify the scale without a browser. */
    if (typeof window !== 'undefined') {
        window.ClinicaIndicadoresCharts = {
            numericValue: numericValue,
            maxValue: maxValue,
            scaleValue: scaleValue,
            barMetrics: barMetrics,
            hasPositiveValue: hasPositiveValue
        };
    }

    var dataElement = document.getElementById('doctor-kpi-data');
    if (!dataElement) {
        return;
    }

    var data;
    try {
        data = JSON.parse(dataElement.textContent || '{}');
    } catch (error) {
        return;
    }

    function emptyState(container, message) {
        container.textContent = message;
        container.classList.add('is-empty');
    }

    function createElement(tag, className, text) {
        var element = document.createElement(tag);
        if (className) {
            element.className = className;
        }
        if (text !== undefined) {
            element.textContent = text;
        }
        return element;
    }

    function createSvgElement(tag, attributes, text) {
        var element = document.createElementNS(SVG_NS, tag);
        Object.keys(attributes || {}).forEach(function (attribute) {
            element.setAttribute(attribute, attributes[attribute]);
        });
        if (text !== undefined) {
            element.textContent = text;
        }
        return element;
    }

    function axisText(value) {
        return value >= 1000 ? Math.round(value / 100) / 10 + 'k' : String(Math.round(value));
    }

    function createBarSvg(config) {
        var items = config.items || [];
        var valueKey = config.valueKey || 'value';
        var width = 720;
        var height = 300;
        var margins = { top: 28, right: 20, bottom: 62, left: 50 };
        var plotWidth = width - margins.left - margins.right;
        var plotHeight = height - margins.top - margins.bottom;
        var metrics = barMetrics(items, valueKey, plotHeight);
        var max = metrics.max;
        var svg = createSvgElement('svg', {
            class: 'doctor-kpi-svg' + (config.chartClass ? ' ' + config.chartClass : ''),
            viewBox: '0 0 ' + width + ' ' + height,
            role: 'img',
            'aria-labelledby': config.id + '-title ' + config.id + '-description',
            preserveAspectRatio: 'none'
        });
        svg.setAttribute('data-chart-responsive', 'true');
        svg.appendChild(createSvgElement('title', { id: config.id + '-title' }, config.title));
        svg.appendChild(createSvgElement('desc', { id: config.id + '-description' }, config.description));

        for (var tick = 0; tick <= 4; tick += 1) {
            var ratio = tick / 4;
            var y = margins.top + plotHeight - ratio * plotHeight;
            svg.appendChild(createSvgElement('line', {
                class: tick === 0 ? 'doctor-kpi-svg-axis' : 'doctor-kpi-svg-gridline',
                x1: margins.left,
                y1: y,
                x2: width - margins.right,
                y2: y
            }));
            svg.appendChild(createSvgElement('text', {
                class: 'doctor-kpi-svg-y-label',
                x: margins.left - 10,
                y: y + 4,
                'text-anchor': 'end'
            }, axisText(max * ratio)));
        }

        var bandWidth = plotWidth / Math.max(items.length, 1);
        var barWidth = Math.max(Math.min(bandWidth * .62, 48), 3);
        items.forEach(function (item, index) {
            var value = metrics.values[index].value;
            var barHeight = metrics.values[index].height;
            var x = margins.left + index * bandWidth + (bandWidth - barWidth) / 2;
            var y = margins.top + plotHeight - barHeight;
            var label = String(item && item.label !== undefined ? item.label : '');
            var bar = createSvgElement('rect', {
                class: 'doctor-kpi-svg-bar' + (item && item.is_invalid_date ? ' is-invalid-date' : ''),
                x: x,
                y: y,
                width: barWidth,
                height: barHeight,
                rx: 4,
                'data-value': value,
                tabindex: '0',
                role: 'img',
                'aria-label': label + ': ' + value
            });
            bar.appendChild(createSvgElement('title', {}, label + ': ' + value));
            svg.appendChild(bar);

            if (items.length <= 18) {
                svg.appendChild(createSvgElement('text', {
                    class: 'doctor-kpi-svg-value',
                    x: x + barWidth / 2,
                    y: Math.max(margins.top + 12, y - 7),
                    'text-anchor': 'middle'
                }, String(value)));
            }

            svg.appendChild(createSvgElement('text', {
                class: 'doctor-kpi-svg-x-label' + (item && item.is_invalid_date ? ' is-invalid-date' : ''),
                x: x + barWidth / 2,
                y: height - 27,
                'text-anchor': 'middle'
            }, label));
        });

        return svg;
    }

    function renderPatientChart(container, items) {
        if (!container || !items || !items.length || !hasPositiveValue(items, ['value'])) {
            if (container) {
                emptyState(container, 'No hay registros para el periodo seleccionado.');
            }
            return;
        }

        container.classList.remove('is-empty');
        container.appendChild(createBarSvg({
            id: 'kpi-patients-svg',
            chartClass: 'doctor-kpi-svg-patients',
            title: 'Pacientes registrados por año',
            description: 'Barras proporcionales a la cantidad de pacientes registrados; las fechas inválidas se muestran por separado.',
            items: items,
            valueKey: 'value'
        }));
    }

    function renderActivityChart(container, items) {
        var keys = [
            { key: 'notes', label: 'Notas subsecuentes', tone: 'notes' },
            { key: 'laboratorios', label: 'Laboratorios', tone: 'laboratorios' },
            { key: 'recetas', label: 'Recetas internas', tone: 'recetas' },
            { key: 'cofepris', label: 'Registros COFEPRIS', tone: 'cofepris' }
        ];
        var keysToCheck = keys.map(function (series) { return series.key; });
        if (!container || !items || !items.length || !hasPositiveValue(items, keysToCheck)) {
            if (container) {
                emptyState(container, 'No hay registros para el periodo seleccionado.');
            }
            return;
        }

        container.classList.remove('is-empty');
        var grid = createElement('div', 'doctor-kpi-series-stack');
        keys.forEach(function (series) {
            var figure = createElement('figure', 'doctor-kpi-series-chart doctor-kpi-series-chart-' + series.tone);
            var caption = createElement('figcaption', 'doctor-kpi-series-heading');
            caption.appendChild(createElement('strong', '', series.label));
            caption.appendChild(createElement('span', '', 'Unidad: registros por periodo'));
            figure.appendChild(caption);
            if (hasPositiveValue(items, [series.key])) {
                figure.appendChild(createBarSvg({
                    id: 'kpi-activity-' + series.key + '-svg',
                    chartClass: 'doctor-kpi-svg-' + series.tone,
                    title: series.label,
                    description: series.label + ', con escala independiente y unidad de registros.',
                    items: items,
                    valueKey: series.key
                }));
            } else {
                figure.appendChild(createElement('div', 'doctor-kpi-series-empty', 'No hay registros para el periodo seleccionado.'));
            }
            grid.appendChild(figure);
        });
        container.appendChild(grid);
    }

    renderPatientChart(document.getElementById('kpi-patients-chart'), data.patientsByYear || []);
    renderActivityChart(document.getElementById('kpi-activity-chart'), data.activitySeries || []);

    var tabs = Array.prototype.slice.call(document.querySelectorAll('[data-kpi-tab]'));
    var panels = Array.prototype.slice.call(document.querySelectorAll('[data-kpi-panel]'));
    function activateTab(tab) {
        var target = tab.getAttribute('data-kpi-tab');
        tabs.forEach(function (item) {
            var active = item === tab;
            item.setAttribute('aria-selected', active ? 'true' : 'false');
            item.tabIndex = active ? 0 : -1;
        });
        panels.forEach(function (panel) {
            var active = panel.id === target;
            panel.hidden = !active;
            panel.setAttribute('aria-hidden', active ? 'false' : 'true');
        });
    }

    tabs.forEach(function (tab, index) {
        tab.addEventListener('click', function () { activateTab(tab); });
        tab.addEventListener('keydown', function (event) {
            var nextIndex = index;
            if (event.key === 'ArrowRight') nextIndex = (index + 1) % tabs.length;
            if (event.key === 'ArrowLeft') nextIndex = (index - 1 + tabs.length) % tabs.length;
            if (event.key === 'Home') nextIndex = 0;
            if (event.key === 'End') nextIndex = tabs.length - 1;
            if (nextIndex !== index) {
                event.preventDefault();
                tabs[nextIndex].focus();
                activateTab(tabs[nextIndex]);
            }
        });
    });

    if (tabs.length) {
        activateTab(tabs[0]);
    }
}());
