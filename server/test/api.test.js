import test from 'node:test';import assert from 'node:assert/strict';import app from '../src/index.js';
test('health endpoint',async()=>{const server=app.listen(0);const port=server.address().port;const r=await fetch(`http://127.0.0.1:${port}/api/health`);assert.equal(r.status,200);assert.equal((await r.json()).ok,true);server.close()});
