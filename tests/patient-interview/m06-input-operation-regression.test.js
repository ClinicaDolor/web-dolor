const fs = require('fs');
const path = require('path');
const vm = require('vm');

const root = path.resolve(__dirname, '../..');
const coordinatorSource = fs.readFileSync(path.join(root, 'public/assets/js/patient-interview-coordinator.js'), 'utf8');
const viewSource = fs.readFileSync(path.join(root, 'app/views/paciente/entrevista.php'), 'utf8');
const controllerSource = fs.readFileSync(path.join(root, 'app/Controllers/PatientInterviewController.php'), 'utf8');
const adapterSource = fs.readFileSync(path.join(root, 'app/Services/ConversationalM06Adapter.php'), 'utf8');
const context = {
    window: {
        Response: global.Response,
        AbortController: global.AbortController,
    },
};
vm.runInNewContext(coordinatorSource, context);
const Coordinator = context.window.PatientInterviewCoordinator;

function assert(condition, message) {
    if (!condition) throw new Error(message);
}

function response(payload, status = 200) {
    return new Response(JSON.stringify(payload), {status, headers: {'Content-Type': 'application/json'}});
}

async function main() {
    assert(viewSource.includes('const setM06Prompt = (card, prompt, state, context = {}, userGesture = false, clearInput = false)'), 'M06 must expose an explicit post-success input-clear path.');
    assert(viewSource.includes("if (input && clearInput)"), 'M06 must not clear the input before persistence/readback succeeds.');
    assert(viewSource.includes("'capturing', nextContext, false, true") && viewSource.includes("'after-save', nextContext, false, true"), 'M06 save transitions must clear the visible input only after confirmation.');
    assert(!viewSource.includes('START_NEW_MEDICATION') && viewSource.includes('data-m06-skip-mode="unknown"') && viewSource.includes('data-m06-skip-mode="omit"'), 'M06 detail questions must expose contextual skip actions and no obsolete incomplete-add action.');
    assert(viewSource.includes('data-m06-stage="initial"') && viewSource.includes('data-m06-stage="after-save"'), 'M06 start/add-another actions must be stage-specific.');
    assert(viewSource.includes('data-m06-composer') && viewSource.includes("composer.hidden = state === 'after-save'"), 'M06 ADD_ANOTHER must hide the text/microphone composer.');
    assert(!viewSource.includes('data-m06-omission-actions') && viewSource.includes('data-m06-omissions-by-record'), 'M06 ADD_ANOTHER must keep historical omission controls out of the active question.');
    assert(viewSource.includes("const canOmit = state === 'capturing'") && viewSource.includes("finishButton.hidden = state === 'capturing'"), 'M06 omission and finish controls must be stage-gated.');
    assert(viewSource.includes("result.textContent = ''; result.hidden = true;") && viewSource.includes("'Medicamento guardado y confirmado.'"), 'M06 save confirmation must not be duplicated in the module result and global status.');
    assert(viewSource.includes('const skipM06Field') && viewSource.includes('m06CurrentField(card)'), 'M06 skip actions must use the active persisted medication field.');
    assert(viewSource.includes('omission:{recordId, field:activeField') && viewSource.includes("state:mode === 'unknown' ? 'UNKNOWN' : 'SKIPPED'"), 'M06 skip actions must persist UNKNOWN/SKIPPED through the existing capture endpoint.');
    assert(!viewSource.includes('data-m06-omission-actions') && viewSource.includes('data-m06-omissions-by-record') && viewSource.includes('data-m06-revisit'), 'M06 historical omission corrections must stay outside the active question.');
    assert(viewSource.includes("card.dataset.m06OmissionRecord = ''") && viewSource.includes('m06SetOmissions(card, {})'), 'Starting a new medication must clear the previous record UI context without changing persisted omissions.');
    assert(viewSource.includes('Escribe el nombre del siguiente medicamento.'), 'M06 second-medication guidance must use the current context.');
    const refreshVoiceMatch = viewSource.match(/const refreshM06QuestionVoice = card => \{([\s\S]*?)\n    \};/);
    assert(refreshVoiceMatch && refreshVoiceMatch[1].includes('if (target) conversationCoordinator.setTarget(target);') && !refreshVoiceMatch[1].includes('target !== conversationCoordinator.snapshot().currentTarget'), 'The real M06 refresh path must re-apply the same target after readback; it must not depend on a changed stable_id.');
    assert(!coordinatorSource.includes('Object.create(response)'), 'The coordinator must not replace a native Response with an object facade.');
    assert(coordinatorSource.includes('response.clone().json()') && coordinatorSource.includes('return response;'), 'The coordinator must inspect a clone and return the untouched native Response.');
    assert(controllerSource.includes("$proposal['record_id'] = (int)$recordId"), 'M06 must forward the explicit record id to the canonical adapter.');
    assert(adapterSource.includes("SELECT GET_LOCK(:name,5)") && adapterSource.includes('beginTransaction()') && adapterSource.includes('commit()') && adapterSource.includes('RELEASE_LOCK'), 'M06 must retain its lock, transaction and release guards.');
    assert(adapterSource.includes("$model->editarMedicamentoPaciente") && adapterSource.includes("'readback' => true"), 'M06 updates must use the existing model and confirmed readback.');
    assert(adapterSource.includes('captureOmission') && adapterSource.includes('M06_OMISSION_READBACK_FAILED'), 'M06 omission persistence must have an explicit readback failure guard.');
    assert(adapterSource.includes('readM06Omissions') && adapterSource.includes("'field_states'"), 'M06 readback must project persisted omission states.');
    assert(controllerSource.includes("$data['omission']") && controllerSource.includes('captureOmission'), 'M06 controller must route omission persistence through the existing endpoint.');

    assert(typeof Response === 'function', 'The regression requires the native Fetch Response implementation available in the Node runtime.');
    const nativeResponseCoordinator = new Coordinator({
        contract: {question_index: {'M06:current-medication:new': {module_id: 'M06', stable_id: 'M06:current-medication:new', voice: {QUESTION: 'Pregunta M06'}}}},
        fetcher: async () => response({resultado: true, proposal: {kind: 'clarification'}}),
    });
    nativeResponseCoordinator.setTarget('M06:current-medication:new');
    const nativeResponse = await nativeResponseCoordinator.request('interpret', '/historia-clinica/entrevista/interpretar', {method: 'POST'});
    assert(nativeResponse instanceof Response, 'The coordinator must return the original native Response.');
    assert(nativeResponse.ok === true && nativeResponse.status === 200, 'Native Response ok/status getters must remain usable.');
    assert(nativeResponse.headers.get('content-type').includes('application/json'), 'Native Response headers must remain usable.');
    assert((await nativeResponse.clone().json()).resultado === true, 'Native Response.clone() must remain usable after coordinator inspection.');
    assert((await nativeResponse.json()).resultado === true, 'Native Response.json() must remain usable by the caller.');
    assert(nativeResponseCoordinator.snapshot().activeOperation === null, 'The operation must settle before the native response is returned.');

    const failed4xx = new Coordinator({fetcher: async () => response({resultado: false, code: 'M06_VERSION_CONFLICT'}, 409)});
    failed4xx.setTarget('M06:current-medication:new');
    const response4xx = await failed4xx.request('save', '/historia-clinica/entrevista/capturar-m06', {method: 'POST'});
    assert(response4xx.ok === false && response4xx.status === 409, 'HTTP 4xx status must remain visible on the native Response.');
    assert((await response4xx.json()).code === 'M06_VERSION_CONFLICT', 'HTTP 4xx JSON must remain readable by the caller.');
    assert(failed4xx.state === 'SAVE_FAILED' && failed4xx.snapshot().activeOperation === null, 'HTTP 4xx must fail closed and release the operation.');

    const failed5xx = new Coordinator({fetcher: async () => response({resultado: false, code: 'SERVER_ERROR'}, 500)});
    failed5xx.setTarget('M06:current-medication:new');
    const response5xx = await failed5xx.request('save', '/historia-clinica/entrevista/capturar-m06', {method: 'POST'});
    assert(response5xx.ok === false && response5xx.status === 500, 'HTTP 5xx status must remain visible on the native Response.');
    await response5xx.json();
    assert(failed5xx.state === 'SAVE_FAILED' && failed5xx.snapshot().activeOperation === null, 'HTTP 5xx must fail closed and release the operation.');

    const networkFailure = new Coordinator({fetcher: async () => { throw new Error('synthetic network failure'); }});
    networkFailure.setTarget('M06:current-medication:new');
    let networkRejected = false;
    try { await networkFailure.request('interpret', '/historia-clinica/entrevista/interpretar', {method: 'POST'}); } catch (error) { networkRejected = error.message === 'synthetic network failure'; }
    assert(networkRejected && networkFailure.snapshot().activeOperation === null, 'Network failures must propagate and release the operation.');

    const payloads = [
        {resultado: true, proposal: {kind: 'm06_proposal', target: 'm06', direct_save: true, can_confirm: true, fields: {medicamento: 'Paracetamol'}}},
        {resultado: true, readback: true, version: 'm06-v2', record: {id: 42, medicamento: 'Paracetamol', descripcion: null}, pending_record: {id: 42}, next_action: 'COMPLETE_MEDICATION', missing: ['descripcion']},
        {resultado: true, proposal: {kind: 'm06_proposal', target: 'm06', direct_save: true, can_confirm: true, fields: {medicamento: 'Paracetamol', descripcion: 'Dolor de cabeza'}, record_id: 42}},
        {resultado: true, readback: true, version: 'm06-v3', record: {id: 42, medicamento: 'Paracetamol', descripcion: 'Dolor de cabeza'}, next_action: 'ADD_ANOTHER_MEDICATION', missing: []},
    ];
    let call = 0;
    const coordinator = new Coordinator({
        patientId: 9301,
        contract: {question_index: {
            'M06:current-medication:new': {module_id: 'M06', stable_id: 'M06:current-medication:new', voice: {QUESTION: '¿Qué medicamento tomas?'}},
            'M06:current-medication:42': {module_id: 'M06', stable_id: 'M06:current-medication:42', voice: {QUESTION: '¿Para qué lo utilizas?'}},
        }},
        fetcher: async () => response(payloads[call++]),
    });

    const runTurn = async target => {
        coordinator.setTarget(target);
        const interpretation = await coordinator.request('auto', '/historia-clinica/entrevista/interpretar', {method: 'POST'});
        const proposal = await interpretation.json();
        assert(proposal.resultado === true, 'M06 interpretation must be accepted.');
        assert(coordinator.snapshot().activeOperation === null, 'Interpretation must release its operation before the save request.');
        const saved = await coordinator.request('auto', '/historia-clinica/entrevista/capturar-m06', {method: 'POST'});
        const readback = await saved.json();
        assert(readback.readback === true, 'M06 save must expose confirmed readback.');
        assert(coordinator.snapshot().activeOperation === null, 'Confirmed M06 save must release its operation.');
    };

    await runTurn('M06:current-medication:new');
    await runTurn('M06:current-medication:42');
    assert(call === 4, 'The two sequential M06 turns must use exactly one interpretation and one save each.');
    assert(coordinator.state === 'RESPONSE_SAVED', 'The second M06 turn must end in the confirmed-save state.');

    const sameTarget = new Coordinator({
        contract: {question_index: {'M06:current-medication:42': {module_id: 'M06', stable_id: 'M06:current-medication:42', voice: {QUESTION: 'Siguiente dato M06'}}}},
        fetcher: async () => response({resultado: true, readback: true, version: 'm06-next'}),
    });
    sameTarget.setTarget('M06:current-medication:42');
    await (await sameTarget.request('save', '/historia-clinica/entrevista/capturar-m06', {method: 'POST'})).json();
    assert(sameTarget.state === 'RESPONSE_SAVED', 'The same-record save must reach RESPONSE_SAVED before the next field.');
    sameTarget.setTarget('M06:current-medication:42');
    assert(sameTarget.state === 'ASKING', 'A same-target progressive question must re-arm ASKING after readback.');
    await (await sameTarget.request('interpret', '/historia-clinica/entrevista/interpretar', {method: 'POST'})).json();
    assert(sameTarget.snapshot().activeOperation === null, 'The next same-target submission must not be reported as a duplicate.');

    let release;
    const concurrent = new Coordinator({fetcher: () => new Promise(resolve => { release = resolve; })});
    concurrent.setTarget('M06:current-medication:new');
    const first = concurrent.request('auto', '/historia-clinica/entrevista/interpretar', {method: 'POST'});
    const duplicate = await concurrent.request('auto', '/historia-clinica/entrevista/interpretar', {method: 'POST'});
    assert((await duplicate.json()).code === 'COORDINATOR_DUPLICATE_REQUEST', 'A genuinely concurrent duplicate must remain protected.');
    release(response({resultado: true, proposal: {kind: 'clarification'}}));
    await (await first).json();
    assert(concurrent.snapshot().activeOperation === null, 'A completed concurrent operation must release its guard.');

    console.log('M06_INPUT_OPERATION_REGRESSION: PASS');
    console.log('INPUT_CLEAR_POLICY: PASS');
    console.log('SEQUENTIAL_INTERPRET_SAVE: PASS');
    console.log('SAME_RECORD_UPDATE_TARGET: PASS');
    console.log('SAME_TARGET_REARM: PASS');
    console.log('FALSE_DUPLICATE_OPERATION: PASS');
    console.log('BACKEND_LOCK_AND_READBACK_GUARDS: PASS_STATIC');
    console.log('NATIVE_RESPONSE_CONTRACT: PASS');
    console.log('HTTP_4XX_5XX_AND_NETWORK: PASS');
    console.log('CONTEXTUAL_BUTTON_STAGES: PASS_STATIC');
    console.log('ADD_ANOTHER_TWO_ACTIONS: PASS_STATIC');
    console.log('ADD_ANOTHER_COMPOSER_HIDDEN: PASS_STATIC');
    console.log('FIELD_SKIP_SCOPE: PASS_STATIC');
    console.log('REAL_MYSQL_HTTP: NOT_EXECUTED');
}

main().catch(error => { console.error(error.message); process.exit(1); });
