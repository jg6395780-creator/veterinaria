export default async function handler(req, res) {
  const railwayBase = 'https://veterinaria-production-fe60.up.railway.app';
  const targetUrl = railwayBase + req.url;

  if (req.method === 'OPTIONS') {
    res.setHeader('Access-Control-Allow-Origin', '*');
    res.setHeader('Access-Control-Allow-Methods', 'GET, POST, OPTIONS');
    res.setHeader('Access-Control-Allow-Headers', 'Content-Type');
    return res.status(200).end();
  }

  const fetchOptions = {
    method: req.method,
    headers: { 'Content-Type': 'application/json' },
  };

  if (req.method === 'POST') {
    fetchOptions.body = JSON.stringify(req.body);
  }

  const response = await fetch(targetUrl, fetchOptions);
  const data = await response.json();

  res.setHeader('Access-Control-Allow-Origin', '*');
  res.status(response.status).json(data);
}
