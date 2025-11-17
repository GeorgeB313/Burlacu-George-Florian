const input = document.getElementById("inputActivitate");
const btn = document.getElementById("btnAdauga");
const lista = document.getElementById("listaActivitati");

const luni = [
    "Ianuarie", "Februarie", "Martie", "Aprilie",
    "Mai", "Iunie", "Iulie", "August",
    "Septembrie", "Octombrie", "Noiembrie", "Decembrie"
];

function adaugaActivitate() {
    const text = input.value.trim();
    if (text === "") return;

    const azi = new Date();
    const zi = azi.getDate();
    const luna = luni[azi.getMonth()];
    const an = azi.getFullYear();
    const dataFormata = `${zi} ${luna} ${an}`;

    const li = document.createElement("li");
    li.textContent = `${text}, adăugată la: ${dataFormata}`;

    lista.appendChild(li);
    input.value = "";
    input.focus();
}

btn.addEventListener("click", adaugaActivitate);

input.addEventListener("keydown", (e) => {
    if (e.key === "Enter") {
        adaugaActivitate();
    }
});
