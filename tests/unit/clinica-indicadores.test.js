const assert = require('assert');
const fs = require('fs');
const path = require('path');
const vm = require('vm');

const sourcePath = path.resolve(__dirname, '../../public/assets/js/clinica-indicadores.js');
const fixturePath = path.resolve(__dirname, '../fixtures/doctor-kpi-chart-fixtures.json');
const source = fs.readFileSync(sourcePath, 'utf8');
const fixture = JSON.parse(fs.readFileSync(fixturePath, 'utf8'));
const context = {
  window: {},
  document: {
    getElementById: () => null,
    querySelectorAll: () => []
  },
  JSON,
  isFinite
};

vm.runInNewContext(source, context, { filename: sourcePath });
const charts = context.window.ClinicaIndicadoresCharts;
assert.ok(charts, 'La API de transformación de gráficos debe estar disponible');

const values = fixture.patientsByYear.map((item) => item.value);
const max = charts.maxValue(fixture.patientsByYear, 'value');
const heights = values.map((value) => charts.scaleValue(value, max, 240));
const metrics = charts.barMetrics(fixture.patientsByYear, 'value', 240);

assert.strictEqual(max, 129);
assert.ok(heights[0] < heights[1]);
assert.ok(heights[1] < heights[2]);
assert.ok(heights[2] < heights[3]);
assert.ok(heights[3] < heights[4]);
assert.strictEqual(heights[4], 240);
assert.strictEqual(metrics.max, 129);
assert.strictEqual(metrics.values[4].height, 240);
assert.ok(metrics.values[2].height < metrics.values[3].height);
assert.strictEqual(charts.scaleValue(0, max, 240), 0);
assert.strictEqual(charts.numericValue('12'), 12);
assert.strictEqual(charts.numericValue(null), 0);
assert.strictEqual(charts.numericValue('no-numero'), 0);
assert.strictEqual(charts.hasPositiveValue(fixture.activitySeries, ['notes', 'laboratorios', 'recetas', 'cofepris']), true);
assert.strictEqual(charts.hasPositiveValue([{notes: 0, laboratorios: 0, recetas: 0, cofepris: 0}], ['notes', 'laboratorios', 'recetas', 'cofepris']), false);
assert.ok(fixture.patientsByYear.some((item) => item.label === 'Sin fecha válida'));
assert.ok(!fixture.patientsByYear.some((item) => item.label === '0'));

console.log('clinica-indicadores.test.js: PASS');
