const express = require('express');
const path = require('path');
const app = express();
const port = process.env.PORT || 3000;

// Route for the main index page
app.get('/', (req, res) => {
    res.sendFile(path.join(__dirname, 'index.html'));
});

// Route for the download page
app.get('/download', (req, res) => {
    res.sendFile(path.join(__dirname, 'download', 'index.html'));
});

// Route for the systems page
// Route for the systems page
app.get('/sistemas', (req, res) => {
    res.sendFile(path.join(__dirname, 'sistema.html'));
});

// Route for the mobile systems page (plural)
app.get('/sistemas-mobile', (req, res) => {
    res.sendFile(path.join(__dirname, 'sistema-mb.html'));
});

// Fallback/Typo route for the mobile systems page (singular)
app.get('/sistema-mobile', (req, res) => {
    res.sendFile(path.join(__dirname, 'sistema-mb.html'));
});

// Serve static files from the 'download' directory specifically for the .exe if needed locally
app.use('/download', express.static(path.join(__dirname, 'download')));

// Serve static files from the 'site' directory (images, etc)
app.use('/site', express.static(path.join(__dirname, 'site')));


// Start the server
app.listen(port, () => {
    console.log(`Server running at http://localhost:${port}/`);
    console.log(`Download page at http://localhost:${port}/download`);
});
