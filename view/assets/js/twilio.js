async function sendMessageng() {
    const message = document.getElementById('messageInit').value;
    fetch('https://n8n-production-fa81.up.railway.app/webhook/757e895d-aff7-4c53-b36b-457ed88c81a3', {
        method: "POST",
        body: JSON.stringify(
            {
                "message": message,
            }
        ),
    })
    .then((response) => {
        return response.json();
    })
    .then((data) => {
        document.body.append(`<p>${data}</p>`)
        console.log(data);
    })
    .catch((error) => {
        console.error('Falha ao fazer a requisição a api do Gemini. erro: ' + error);
    })

}