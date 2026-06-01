export default async function handler(req, res) {
  const railwayUrl = 'https://veterinaria-production-fe60.up.railway.app' + req.url;

  const response = await fetch(railwayUrl, {
    method: req.method,
    headers: { 'Content-Type': 'application/json' },
    body: req.method !== 'GET' ? JSON.stringify(req.body) : undefined,
  });

  const data = await response.json();

  res.setHeader('Access-Control-Allow-Origin', '*');
  res.setHeader('Access-Control-Allow-Methods', 'GET, POST, OPTIONS');
  res.json(data);
}
