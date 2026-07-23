const express = require('express');
const crypto = require('crypto');
const fs = require('fs/promises');
const path = require('path');

const app = express();
const port = Number(process.env.PORT) || 3000;
const adminUser = process.env.LEADS_USER || '';
const adminPassword = process.env.LEADS_PASSWORD || '';
const dataDir = path.resolve(process.env.DATA_DIR || path.join(__dirname, 'data'));
const leadsFile = path.join(dataDir, 'leads.json');
const allowedStatuses = new Set(['novo', 'contatado', 'qualificado', 'fechado', 'descartado']);
const questionKeys = [
  'perfil', 'nicho', 'photoshop', 'volume', 'tempo50', 'gargalo', 'mais10',
  'limite_processo_atual', 'capacidade_atual'
];
const rateLimits = new Map();
const adminRateLimits = new Map();
const adminWindowMs = Number(process.env.ADMIN_RATE_WINDOW_MS) || 15 * 60 * 1000;
const adminMaxAttempts = Number(process.env.ADMIN_RATE_MAX_ATTEMPTS) || 5;
let writeQueue = Promise.resolve();

if (!adminUser || !adminPassword) {
  console.error('LEADS_USER e LEADS_PASSWORD precisam estar configurados no ambiente.');
  process.exit(1);
}
if (adminPassword.length < 20) {
  console.error('LEADS_PASSWORD precisa ter pelo menos 20 caracteres.');
  process.exit(1);
}

app.disable('x-powered-by');
app.set('trust proxy', Number(process.env.TRUST_PROXY || 1));
app.use(express.json({ limit: '30kb', type: 'application/json' }));
app.use((req, res, next) => {
  res.set({
    'X-Content-Type-Options': 'nosniff',
    'X-Frame-Options': 'SAMEORIGIN',
    'Referrer-Policy': 'strict-origin-when-cross-origin',
    'Permissions-Policy': 'camera=(), microphone=(), geolocation=()',
    'Strict-Transport-Security': 'max-age=31536000; includeSubDomains'
  });
  next();
});

function clean(value, maxLength) {
  return typeof value === 'string' ? value.trim().replace(/[\u0000-\u001f\u007f]/g, '').slice(0, maxLength) : '';
}

function safeEqual(left, right) {
  const a = Buffer.from(String(left));
  const b = Buffer.from(String(right));
  return a.length === b.length && crypto.timingSafeEqual(a, b);
}

function basicAuth(req, res, next) {
  res.set('Cache-Control', 'no-store');
  const now = Date.now();
  const key = req.ip;
  const previous = adminRateLimits.get(key);
  const entry = !previous || now - previous.startedAt >= adminWindowMs
    ? { startedAt: now, count: 0 }
    : previous;
  adminRateLimits.set(key, entry);

  if (entry.count >= adminMaxAttempts) {
    const retryAfter = Math.ceil((adminWindowMs - (now - entry.startedAt)) / 1000);
    res.set('Retry-After', String(Math.max(1, retryAfter)));
    return res.status(429).type('text').send('Muitas tentativas. Tente novamente mais tarde.');
  }

  const match = /^Basic (.+)$/i.exec(req.get('authorization') || '');
  if (match) {
    try {
      const decoded = Buffer.from(match[1], 'base64').toString('utf8');
      const separator = decoded.indexOf(':');
      const user = decoded.slice(0, separator);
      const password = decoded.slice(separator + 1);
      if (separator >= 0 && safeEqual(user, adminUser) && safeEqual(password, adminPassword)) {
        adminRateLimits.delete(key);
        return next();
      }
    } catch (_) {}
    entry.count += 1;
  }
  res.set('WWW-Authenticate', 'Basic realm="Smart Encartes Leads", charset="UTF-8"');
  return res.status(401).type('text').send('Autenticacao necessaria.');
}

function rateLimit(req, res, next) {
  const now = Date.now();
  const windowMs = 10 * 60 * 1000;
  const key = req.ip;
  const previous = rateLimits.get(key);
  const entry = !previous || now - previous.startedAt > windowMs
    ? { startedAt: now, count: 0 }
    : previous;
  entry.count += 1;
  rateLimits.set(key, entry);

  if (rateLimits.size > 1000) {
    for (const [ip, value] of rateLimits) {
      if (now - value.startedAt > windowMs) rateLimits.delete(ip);
    }
  }
  if (entry.count > 10) return res.status(429).json({ error: 'Muitas tentativas. Aguarde alguns minutos.' });
  next();
}

async function ensureStore() {
  await fs.mkdir(dataDir, { recursive: true });
  try {
    await fs.access(leadsFile);
  } catch (_) {
    await fs.writeFile(leadsFile, '[]\n', { encoding: 'utf8', flag: 'wx', mode: 0o600 }).catch(error => {
      if (error.code !== 'EEXIST') throw error;
    });
  }
}

async function readLeads() {
  await ensureStore();
  const raw = await fs.readFile(leadsFile, 'utf8');
  const data = JSON.parse(raw);
  if (!Array.isArray(data)) throw new Error('O arquivo de leads nao contem uma lista.');
  return data;
}

async function writeLeads(leads) {
  await ensureStore();
  const temporary = path.join(dataDir, 'leads-' + process.pid + '-' + Date.now() + '.tmp');
  await fs.writeFile(temporary, JSON.stringify(leads, null, 2) + '\n', { encoding: 'utf8', mode: 0o600 });
  await fs.rename(temporary, leadsFile);
}

function mutateLeads(mutator) {
  const operation = writeQueue.then(async () => {
    const leads = await readLeads();
    const result = mutator(leads);
    await writeLeads(leads);
    return result;
  });
  writeQueue = operation.catch(() => {});
  return operation;
}

function sanitizeAnswers(input) {
  const result = {};
  if (!input || typeof input !== 'object' || Array.isArray(input)) return result;
  for (const key of questionKeys) {
    const answer = input[key];
    if (!answer || typeof answer !== 'object') continue;
    result[key] = {
      value: clean(answer.value, 200),
      score: Number.isFinite(Number(answer.score)) ? Number(answer.score) : 0
    };
  }
  const summary = input.resultado;
  if (summary && typeof summary === 'object') {
    result.resultado = {};
    for (const key of ['percent', 'dailyProducts', 'currentMinutes50', 'savedMinutes', 'reduction', 'designerCount', 'currentClientCapacity', 'automatedClientCapacity', 'extraClients', 'capacityMultiplier']) {
      const value = Number(summary[key]);
      if (Number.isFinite(value)) result.resultado[key] = value;
    }
    result.resultado.level = clean(summary.level, 100);
  }
  return result;
}

function validEmail(email) {
  return !email || /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email);
}

app.get('/healthz', (req, res) => res.set('Cache-Control', 'no-store').json({ ok: true }));
app.get('/', (req, res) => res.sendFile(path.join(__dirname, 'index.html')));
app.get('/diagnostico', (req, res) => res.sendFile(path.join(__dirname, 'diagnostico.html')));
app.get('/download', (req, res) => res.sendFile(path.join(__dirname, 'download', 'index.html')));
app.get('/update', (req, res) => res.sendFile(path.join(__dirname, 'update', 'index.html')));
app.get('/sistemas', (req, res) => res.sendFile(path.join(__dirname, 'sistema.html')));
app.get(['/sistemas-mobile', '/sistema-mobile'], (req, res) => res.sendFile(path.join(__dirname, 'sistema-mb.html')));

app.post('/api/leads', rateLimit, async (req, res, next) => {
  try {
    const name = clean(req.body.name, 100);
    const phoneDigits = clean(req.body.whatsapp, 20).replace(/\D/g, '');
    const whatsapp = phoneDigits.length === 10 || phoneDigits.length === 11 ? '55' + phoneDigits : phoneDigits;
    const email = clean(req.body.email, 160).toLowerCase();
    const website = clean(req.body.website, 200);
    if (website) return res.status(201).json({ ok: true });
    if (name.length < 2) return res.status(400).json({ error: 'Informe o nome.' });
    if (whatsapp.length < 10 || whatsapp.length > 15) return res.status(400).json({ error: 'Informe um WhatsApp valido.' });
    if (!validEmail(email)) return res.status(400).json({ error: 'Informe um e-mail valido.' });
    if (req.body.consent !== true) return res.status(400).json({ error: 'Consentimento necessario.' });

    const lead = {
      id: crypto.randomUUID(),
      createdAt: new Date().toISOString(),
      updatedAt: new Date().toISOString(),
      status: 'novo',
      name,
      whatsapp,
      email,
      source: clean(req.body.source, 80) || 'diagnostico',
      consent: true,
      answers: sanitizeAnswers(req.body.answers)
    };
    await mutateLeads(leads => leads.push(lead));
    res.status(201).json({ ok: true, id: lead.id });
  } catch (error) {
    next(error);
  }
});

app.get('/leads', basicAuth, (req, res) => res.sendFile(path.join(__dirname, 'leads.html')));

app.get('/api/admin/leads', basicAuth, async (req, res, next) => {
  try {
    const leads = await readLeads();
    leads.sort((a, b) => String(b.createdAt).localeCompare(String(a.createdAt)));
    res.json({ leads });
  } catch (error) {
    next(error);
  }
});

app.patch('/api/admin/leads/:id', basicAuth, async (req, res, next) => {
  try {
    const status = clean(req.body.status, 30);
    if (!allowedStatuses.has(status)) return res.status(400).json({ error: 'Status invalido.' });
    const lead = await mutateLeads(leads => {
      const found = leads.find(item => item.id === req.params.id);
      if (!found) return null;
      found.status = status;
      found.updatedAt = new Date().toISOString();
      return found;
    });
    if (!lead) return res.status(404).json({ error: 'Lead nao encontrado.' });
    res.json({ lead });
  } catch (error) {
    next(error);
  }
});

app.delete('/api/admin/leads/:id', basicAuth, async (req, res, next) => {
  try {
    const removed = await mutateLeads(leads => {
      const index = leads.findIndex(item => item.id === req.params.id);
      if (index < 0) return false;
      leads.splice(index, 1);
      return true;
    });
    if (!removed) return res.status(404).json({ error: 'Lead nao encontrado.' });
    res.status(204).end();
  } catch (error) {
    next(error);
  }
});

app.use('/download', express.static(path.join(__dirname, 'download')));
app.use('/update', express.static(path.join(__dirname, 'update')));
app.use('/site', express.static(path.join(__dirname, 'site')));

app.use((error, req, res, next) => {
  console.error(error);
  if (res.headersSent) return next(error);
  if (error.type === 'entity.parse.failed') return res.status(400).json({ error: 'JSON invalido.' });
  res.status(500).json({ error: 'Erro interno do servidor.' });
});

ensureStore()
  .then(() => app.listen(port, '0.0.0.0', () => {
    console.log('Servidor rodando na porta ' + port);
    console.log('Diagnostico: http://localhost:' + port + '/diagnostico');
    console.log('Prancheta: http://localhost:' + port + '/leads');
    console.log('Dados persistentes em ' + dataDir);
    if (!adminPassword) console.warn('ATENCAO: defina LEADS_PASSWORD para liberar a prancheta.');
  }))
  .catch(error => {
    console.error('Nao foi possivel iniciar o armazenamento de leads:', error);
    process.exit(1);
  });
