export function getBaseUrl() {
    const protocol = window.location.protocol;
    const host = window.location.hostname;
    const port = window.location.port;
    
    // If running via Live Server (port 5500), route the backend requests to WampServer (port 80)
    if (port === "5500") {
        const targetHost = (host === "127.0.0.1" || host === "") ? "localhost" : host;
        return `${protocol}//${targetHost}/painel_bca/server/api/v1`;
    }
    
    // If opened via local file protocol, fallback to standard localhost
    if (protocol === "file:") {
        return "http://localhost/painel_bca/server/api/v1";
    }
    
    // Otherwise, dynamically construct the base URL using current host and port
    const portStr = port ? `:${port}` : '';
    return `${protocol}//${host}${portStr}/painel_bca/server/api/v1`;
}