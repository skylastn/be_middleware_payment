const fs = require('fs');
const path = require('path');
const https = require('https');

const apiKey = process.env.POSTMAN_API_KEY;
const collectionId = process.env.POSTMAN_COLLECTION_ID || process.env.POSTMAN_COLLECTION_UID;
const collectionPath = process.env.POSTMAN_COLLECTION_PATH || path.join(__dirname, '..', 'postman', 'MiddleWare_Payment.postman_collection.json');

if (!apiKey) {
    console.error('❌ Error: POSTMAN_API_KEY is not set in environment or repository secrets.');
    process.exit(1);
}

if (!fs.existsSync(collectionPath)) {
    console.error(`❌ Error: Collection file not found at ${collectionPath}`);
    process.exit(1);
}

const rawContent = fs.readFileSync(collectionPath, 'utf8');
let localCollection;
try {
    localCollection = JSON.parse(rawContent);
} catch (err) {
    console.error('❌ Error parsing collection JSON:', err.message);
    process.exit(1);
}

function normalizeStructure(obj) {
    if (!obj || typeof obj !== 'object') return obj;
    if (Array.isArray(obj)) return obj.map(normalizeStructure);
    const sorted = {};
    Object.keys(obj)
        .filter((k) => !['_postman_id', '_exporter_id', '_collection_link', 'updatedAt', 'createdAt', 'uid', 'id'].includes(k))
        .sort()
        .forEach((k) => {
            sorted[k] = normalizeStructure(obj[k]);
        });
    return sorted;
}

function httpsRequest(options, data = null) {
    return new Promise((resolve, reject) => {
        const req = https.request(options, (res) => {
            let body = '';
            res.on('data', (chunk) => (body += chunk));
            res.on('end', () => resolve({ statusCode: res.statusCode, body }));
        });
        req.on('error', reject);
        if (data) req.write(data);
        req.end();
    });
}

async function run() {
    console.log(`🚀 Checking Postman collection '${localCollection.info?.name || 'MiddleWare Payment'}'...`);

    // If updating an existing collection, check remote version first to avoid redundant writes
    if (collectionId) {
        try {
            const getUrl = new URL(`https://api.getpostman.com/collections/${collectionId}`);
            const checkRes = await httpsRequest({
                hostname: getUrl.hostname,
                port: 443,
                path: getUrl.pathname,
                method: 'GET',
                headers: {
                    'X-Api-Key': apiKey,
                    Accept: 'application/json',
                },
            });

            if (checkRes.statusCode === 200) {
                const remoteData = JSON.parse(checkRes.body);
                const remoteCollection = remoteData.collection;

                const normLocal = JSON.stringify(normalizeStructure(localCollection.item || localCollection));
                const normRemote = JSON.stringify(normalizeStructure(remoteCollection.item || remoteCollection));

                if (normLocal === normRemote) {
                    console.log('⏭️  Remote Postman collection is already up to date with same structure. Skipped sync.');
                    process.exit(0);
                }
            }
        } catch (e) {
            console.log('⚠️ Could not verify remote collection structure, proceeding with direct sync.');
        }
    }

    const payload = JSON.stringify({ collection: localCollection });
    const targetUrl = collectionId
        ? `https://api.getpostman.com/collections/${collectionId}`
        : 'https://api.getpostman.com/collections';
    const method = collectionId ? 'PUT' : 'POST';

    console.log(`📡 Syncing: ${method} -> ${targetUrl}`);
    const parsedUrl = new URL(targetUrl);
    const options = {
        hostname: parsedUrl.hostname,
        port: 443,
        path: parsedUrl.pathname + parsedUrl.search,
        method: method,
        headers: {
            'Content-Type': 'application/json',
            'X-Api-Key': apiKey,
            'Content-Length': Buffer.byteLength(payload),
        },
    };

    const res = await httpsRequest(options, payload);
    if (res.statusCode >= 200 && res.statusCode < 300) {
        const data = JSON.parse(res.body);
        const uid = data?.collection?.uid || data?.collection?.id || collectionId || 'OK';
        console.log(`✅ Success! Postman collection synced successfully (UID: ${uid}).`);
    } else {
        console.error(`❌ Postman API Error (HTTP ${res.statusCode}):`, res.body);
        process.exit(1);
    }
}

run().catch((err) => {
    console.error('❌ Sync failed:', err);
    process.exit(1);
});
