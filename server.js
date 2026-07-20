const express = require('express');
const path = require('path');

const app = express();
const port = process.env.PORT || 3000;

// Página principal
app.get('/', (req, res) => {
    res.sendFile(path.join(__dirname, 'index.html'));
});

// Página de download
app.get('/download', (req, res) => {
    res.sendFile(path.join(__dirname, 'download', 'index.html'));
});

// Página de atualização
app.get('/update', (req, res) => {
    res.sendFile(path.join(__dirname, 'update', 'index.html'));
});

// Página de sistemas
app.get('/sistemas', (req, res) => {
    res.sendFile(path.join(__dirname, 'sistema.html'));
});

// Página de sistemas mobile
app.get('/sistemas-mobile', (req, res) => {
    res.sendFile(path.join(__dirname, 'sistema-mb.html'));
});

// Rota alternativa
app.get('/sistema-mobile', (req, res) => {
    res.sendFile(path.join(__dirname, 'sistema-mb.html'));
});

// Arquivos estáticos da pasta download
app.use('/download', express.static(path.join(__dirname, 'download')));

// Arquivos estáticos da pasta update
app.use('/update', express.static(path.join(__dirname, 'update')));

// Arquivos estáticos da pasta site
app.use('/site', express.static(path.join(__dirname, 'site')));

// Iniciar servidor
app.listen(port, '0.0.0.0', () => {
    console.log(`Servidor rodando na porta ${port}`);
    console.log(`Página inicial: http://localhost:${port}/`);
    console.log(`Download: http://localhost:${port}/download`);
    console.log(`Atualização: http://localhost:${port}/update`);
});
