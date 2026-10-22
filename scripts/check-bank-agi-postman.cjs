const fs = require('node:fs');
const vm = require('node:vm');
const assert = require('node:assert/strict');
const crypto = require('node:crypto');
const collection = JSON.parse(fs.readFileSync('postman/MiddleWare_Payment.postman_collection.json', 'utf8'));
const folder = collection.item.find(x => x.name === 'Bank AGI (UAT)');
const script = folder.event[0].script.exec.join('\n');
const {privateKey, publicKey} = crypto.generateKeyPairSync('rsa', {modulusLength: 2048});
const values = new Map([['agi_private_key', privateKey.export({type: 'pkcs8', format: 'pem'})], ['agi_client_key', 'client'], ['agi_client_secret', Buffer.from('secret').toString('base64')], ['agi_access_token', 'token'], ['agi_reference', 'reference'], ['agi_expired_timestamp', '2020-01-01T00:00:00+07:00']]);
let wire;
const rs = {hextob64: x => Buffer.from(x, 'hex').toString('base64'), KJUR: {crypto: {
    Signature: class {init(key) { this.key = key; } updateString(text) { this.text = text; } sign() { return crypto.sign('RSA-SHA256', Buffer.from(this.text), this.key).toString('hex'); }},
    Util: {hashString: text => crypto.createHash('sha256').update(text).digest('hex')},
    Mac: class {constructor(options) { this.key = Buffer.from(options.pass.b64, 'base64'); } updateString(text) { this.text = text; } doFinal() { return crypto.createHmac('sha512', this.key).update(this.text).digest('hex'); }}
}}};
function run(name, endpoint = 'notify-qris') {
    const replace = text => text.replace(/\{\{([^}]+)\}\}/g, (_, key) => key === '$guid' ? crypto.randomUUID() : String(values.get(key) || ''));
    const pm = {info: {requestName: name}, require: () => rs, environment: {get: key => values.get(key), set: (key, value) => values.set(key, value), unset: key => values.delete(key)}, variables: {replaceIn: replace}, request: {method: 'POST', url: {toString: () => `https://uat.example/api/bank/agi/${endpoint}`}, headers: {get: () => 'Bearer {{agi_access_token}}'}, body: {raw: '{"ref":"{{agi_reference}}", "name":"Artha Graha"}', update: text => { wire = text; }}}};
    vm.runInNewContext(`(function(){${script}\n})()`, {pm, Date});
    if (endpoint === 'access-token') return;
    const timestamp = values.get('agi_timestamp');
    const hash = crypto.createHash('sha256').update(wire.replace(/\s+/g, '')).digest('hex');
    return crypto.createHmac('sha512', 'secret').update(`POST:/api/bank/agi/notify-qris:token:${hash}:${timestamp}`).digest('hex');
}
assert.equal(values.get('agi_signature'), undefined);
assert.equal(run('11. Payment Notify - Success'), values.get('agi_signature'));
assert.ok(!wire.includes('{{'));
const firstId = values.get('agi_external_id');
assert.equal(run('6. Conflict - Reused X-EXTERNAL-ID'), values.get('agi_signature'));
assert.equal(values.get('agi_external_id'), firstId);
assert.equal(run('4. Missing Mandatory Field'), values.get('agi_signature'));
assert.notEqual(values.get('agi_external_id'), firstId);
assert.notEqual(run('3. Unauthorized Signature'), values.get('agi_signature'));
assert.equal(run('5d. Unauthorized - Expired X-TIMESTAMP'), values.get('agi_signature'));
assert.equal(values.get('agi_timestamp'), values.get('agi_expired_timestamp'));
assert.equal(run('11. Payment Notify - Success'), values.get('agi_signature'));
assert.notEqual(values.get('agi_timestamp'), values.get('agi_expired_timestamp'));
run('1. Access Token', 'access-token');
assert.ok(crypto.verify('RSA-SHA256', Buffer.from(`client|${values.get('agi_timestamp')}`), publicKey, Buffer.from(values.get('agi_asymmetric_signature'), 'base64')));
assert.equal(values.get('agi_access_token'), undefined);
function check(items) { for (const item of items) { if (item.item) check(item.item); else for (const event of item.event || []) { assert.notEqual(event.listen, 'prerequest'); new Function(event.script.exec.join('\n')); } } }
check(folder.item);
assert.ok(!collection.event.some(event => event.script.exec.join('\n').includes('Package.Signature')));
console.log('Bank AGI Postman checks passed: HMAC, RSA, resolved body, timestamp reset, external ID, script syntax. Package API simulated; verify real Postman package loading separately.');
