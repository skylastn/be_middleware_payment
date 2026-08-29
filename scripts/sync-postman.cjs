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
let collectionJson;
try {
    collectionJson = JSON.parse(rawContent);
} catch (err) {
    console.error('❌ Error parsing collection JSON:', err.message);
    process.exit(1);
}

const payload = JSON.stringify({ collection: collectionJson });

const url = collectionId
    ? `https://api.getpostman.com/collections/${collectionId}`
    : 'https://api.getpostman.com/collections';
const method = collectionId ? 'PUT' : 'POST';

console.log(`🚀 Syncing Postman collection '${collectionJson.info?.name || 'MiddleWare Payment'}'...`);
console.log(`📡 Method: ${method} -> ${url}`);

const parsedUrl = new URL(url);
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

const req = https.request(options, (res) => {
    let responseBody = '';
    res.on('data', (chunk) => {
        responseBody += chunk;
    });

    res.on('end', () => {
        if (res.statusCode >= 200 && res.statusCode < 300) {
            const data = JSON.parse(responseBody);
            const uid = data?.collection?.uid || data?.collection?.id || collectionId || 'OK';
            console.log(`✅ Success! Postman collection synced successfully (UID: ${uid}).`);
            process.exit(0);
        } else {
            console.error(`❌ Postman API Error (HTTP ${res.statusCode}):`, responseBody);
            process.exit(1);
        }
    });
});

req.on('error', (err) => {
    console.error('❌ Network request error:', err.message);
    process.exit(1);
});

req.write(payload);
req.end();
