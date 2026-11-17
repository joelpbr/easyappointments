// Fixed-target LAN gateway. The application remains on an internal Docker network.
const http = require('node:http');
const server = http.createServer((request, response) => {
    const upstream = http.request({
        hostname: 'app', port: 80, method: request.method, path: request.url,
        headers: {...request.headers, connection: 'close'},
    }, (result) => {
        response.writeHead(result.statusCode, result.headers);
        result.pipe(response);
    });
    upstream.on('error', () => {
        if (!response.headersSent) response.writeHead(502, {'Content-Type': 'text/plain'});
        response.end('La consulta encara no esta disponible.');
    });
    request.on('aborted', () => upstream.destroy());
    request.pipe(upstream);
});
server.listen(8080, '0.0.0.0');
