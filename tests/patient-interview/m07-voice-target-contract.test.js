const fs = require('fs');
const path = require('path');
const vm = require('vm');

const view = fs.readFileSync(path.resolve(__dirname, '../../app/views/paciente/entrevista.php'), 'utf8');
const coordinatorSource = fs.readFileSync(path.resolve(__dirname, '../../public/assets/js/patient-interview-coordinator.js'), 'utf8');

function assert(condition, message) {
    if (!condition) throw new Error(message);
}

assert(view.includes('const isM07AnswerableStage = stage =>'), 'M07 answerability guard missing.');
assert(view.includes("card?.dataset.m07WriteEnabled === '1'") && view.includes("resolver.state === 'ANSWERABLE'"), 'M07 voice target must require an answerable server state.');
assert(view.includes("const isM07BlockedStage = stage => stageModuleId(stage) === 'M07' && !isM07AnswerableStage(stage);"), 'M07 blocked voice guard missing.');
assert(view.includes("|| isM07AnswerableStage(stage)"), 'Authorized M07 stages must use the canonical question target.');
assert(view.includes('const blockedEvent = moduleBlockedVoiceEventFor(target);') && view.includes('if (blockedEvent) {') && view.includes('setStatus(blockedEvent.text ||'), 'Blocked M07 help must remain an informational block, not a clinical HELP/question narration.');
assert(view.includes("const blockedM07 = moduleBlockedVoiceEventFor(snapshot.currentTarget);"), 'M07 startup must distinguish module-blocked narration.');
assert(view.includes("const question = blockedM07 || conversationCoordinator.voiceEvent(action ? 'ACTION' : 'QUESTION', snapshot.currentTarget, false);"), 'M07 startup event selection is not fail-closed.');

const context = {window: {AbortController: global.AbortController, Response: global.Response}};
vm.runInNewContext(coordinatorSource, context);
const Coordinator = context.window.PatientInterviewCoordinator;
const contract = {
    modules: {
        M07: {
            questions: [{
                module_id: 'M07',
                stable_id: 'M07:question:1',
                question_id: 1,
                visible_text: 'Pregunta M07 sintética.',
                voice: {
                    QUESTION: 'Pregunta M07 sintética.',
                    HELP: 'Ayuda M07 sintética.',
                },
            }],
            voice: {MODULE_BLOCKED: 'M07 permanece bloqueado para escritura.'},
        },
    },
    question_index: {
        'M07:question:1': {
            module_id: 'M07',
            stable_id: 'M07:question:1',
            question_id: 1,
            visible_text: 'Pregunta M07 sintética.',
            voice: {QUESTION: 'Pregunta M07 sintética.', HELP: 'Ayuda M07 sintética.'},
        },
    },
};

const events = [];
const coordinator = new Coordinator({contract, onVoiceEvent: event => events.push(event)});
coordinator.setTarget('M07:question:1');
assert(events.at(-1)?.stable_id === 'M07:question:1', 'Authorized M07 simulation must preserve the canonical stable_id.');
assert(events.at(-1)?.text === 'Pregunta M07 sintética.', 'Authorized M07 simulation must expose the question voice text.');

coordinator.setTarget('M07');
const blocked = coordinator.voiceEvent('MODULE_BLOCKED', 'M07', false);
assert(blocked.text === 'M07 permanece bloqueado para escritura.', 'Blocked M07 simulation must use module-blocked text.');
assert(blocked.stable_id === 'M07:question:1', 'The coordinator must retain the source question identity in blocked context.');

console.log('M07_VOICE_TARGET_CONTRACT: PASS');
console.log('M07_BLOCKED_NO_CLINICAL_QUESTION_TRIGGER: PASS_STATIC');
console.log('M07_AUTHORIZED_CANONICAL_TARGET: PASS_SIMULATED_CONTRACT');
console.log('M06_RUNTIME: NOT_EXECUTED');
