import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { fileURLToPath } from 'node:url';
import { dirname, resolve } from 'node:path';

const testDirectory = dirname(fileURLToPath(import.meta.url));
const viewPath = resolve(testDirectory, '../../app/views/historia-clinica-paciente/antecedentes-familiares.php');
const viewSource = readFileSync(viewPath, 'utf8');
const flowStart = viewSource.indexOf('    //---------- FLUJO DE COMENTARIO Y FINALIZACION DEL MODULO ----------');
const flowEnd = viewSource.indexOf('    </script>', flowStart);

if (flowStart < 0 || flowEnd < 0) {
  throw new Error('No se encontró el flujo P04 de comentario y finalización.');
}

const flowSource = viewSource.slice(flowStart, flowEnd);

const okResponse = () => ({
  ok: true,
  status: 200,
  json: async () => ({ resultado: true }),
});

const failedResponse = (status) => ({
  ok: false,
  status,
  json: async () => ({ resultado: false }),
});

function createFlowHarness(responses, { comment = 'Comentario sintético' } = {}) {
  const calls = [];
  const messages = [];
  let responseIndex = 0;
  const button = {
    disabled: false,
    attributes: {},
    setAttribute(name, value) {
      this.attributes[name] = value;
    },
  };
  const textarea = { value: comment, disabled: false };
  const document = {
    querySelector: () => button,
    getElementById: (id) => (id === 'comentarioModulos' ? textarea : null),
  };
  const window = { location: { href: '' } };
  const $ = () => ({ show() {}, fadeOut() {} });
  const fetch = async (url, options) => {
    calls.push({ url, options });
    const response = responses[responseIndex++];
    if (response instanceof Error) throw response;
    return response;
  };
  const p04SetMessage = (message) => messages.push(message);
  const p04ErrorMessage = (error) => {
    if (error?.status === 401 || error?.status === 403) return 'No fue posible completar la operación.';
    if (error?.status === 429) return 'Demasiados intentos. Espera un momento e inténtalo de nuevo.';
    return 'No fue posible completar la operación. Inténtalo nuevamente.';
  };

  const build = new Function(
    'document', '$', 'window', 'fetch', 'p04SetMessage', 'p04ErrorMessage',
    `${[
      'let p04PendingRequest = false;',
      'let p04FinalizationInFlight = false;',
      'let p04FinalizationCommentSaved = false;',
      'let p04FinalizationCommentContext = null;',
      flowSource,
      'return { agregarComentario, FinalizarModuloPaciente, state: () => ({ p04PendingRequest, p04FinalizationInFlight, commentSaved: p04FinalizationCommentSaved, location: window.location.href }) };',
    ].join('\n')}`
  );

  return {
    flow: build(document, $, window, fetch, p04SetMessage, p04ErrorMessage),
    calls,
    messages,
    button,
    textarea,
  };
}

async function run() {
  {
    const harness = createFlowHarness([okResponse(), okResponse()]);
    assert.equal(await harness.flow.agregarComentario(2, 9301, 'Paciente'), true);
    assert.equal(harness.calls.length, 2);
    assert.match(harness.calls[0].url, /agregar-comentario-modulo$/);
    assert.match(harness.calls[1].url, /finalizar-modulo-paciente$/);
    assert.equal(harness.flow.state().location, '/historia-clinica');
    assert.equal(harness.flow.state().p04PendingRequest, false);
    assert.equal(harness.button.disabled, false);
  }

  {
    const harness = createFlowHarness([failedResponse(500)]);
    assert.equal(await harness.flow.agregarComentario(2, 9301, 'Paciente'), false);
    assert.equal(harness.calls.length, 1);
    assert.equal(harness.flow.state().location, '');
    assert.match(harness.messages.at(-1), /No fue posible/);
    assert.equal(harness.button.disabled, false);
  }

  {
    const harness = createFlowHarness([okResponse(), failedResponse(503), okResponse()]);
    assert.equal(await harness.flow.agregarComentario(2, 9301, 'Paciente'), false);
    assert.equal(harness.calls.length, 2);
    assert.equal(await harness.flow.agregarComentario(2, 9301, 'Paciente'), true);
    assert.equal(harness.calls.length, 3);
    assert.equal(harness.calls.filter((call) => /agregar-comentario-modulo$/.test(call.url)).length, 1);
    assert.equal(harness.calls.filter((call) => /finalizar-modulo-paciente$/.test(call.url)).length, 2);
    assert.equal(harness.flow.state().location, '/historia-clinica');
  }

  {
    const harness = createFlowHarness([new TypeError('network')]);
    assert.equal(await harness.flow.agregarComentario(2, 9301, 'Paciente'), false);
    assert.equal(harness.calls.length, 1);
    assert.match(harness.messages.at(-1), /No fue posible/);
    assert.equal(harness.flow.state().p04PendingRequest, false);
    assert.equal(harness.button.disabled, false);
  }

  {
    const harness = createFlowHarness([okResponse(), okResponse()]);
    const [first, second] = await Promise.all([
      harness.flow.agregarComentario(2, 9301, 'Paciente'),
      harness.flow.agregarComentario(2, 9301, 'Paciente'),
    ]);
    assert.equal(first, true);
    assert.equal(second, false);
    assert.equal(harness.calls.length, 2);
  }

  {
    const harness = createFlowHarness([okResponse(), okResponse()], { comment: '' });
    assert.equal(await harness.flow.agregarComentario(2, 9301, 'Paciente'), true);
    const commentPayload = JSON.parse(harness.calls[0].options.body);
    assert.equal(commentPayload.comentarioModulos, '');
  }

  console.log('P04 finalization flow: PASS');
}

run().catch((error) => {
  console.error('P04 finalization flow: FAIL');
  console.error(error);
  process.exitCode = 1;
});
