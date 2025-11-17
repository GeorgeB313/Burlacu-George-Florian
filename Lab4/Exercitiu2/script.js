const detalii = document.getElementById("detalii");
const btn = document.getElementById("btnDetalii");
const dataProdus = document.getElementById("dataProdus");

const luni = [
    "Ianuarie", "Februarie", "Martie", "Aprilie",
    "Mai", "Iunie", "Iulie", "August",
    "Septembrie", "Octombrie", "Noiembrie", "Decembrie"
];

// Set current date in Romanian format
const azi = new Date();
const zi = azi.getDate();
const luna = luni[azi.getMonth()];
const an = azi.getFullYear();
dataProdus.textContent = `${zi} ${luna} ${an}`;

btn.addEventListener("click", () => {
    const ascuns = detalii.classList.toggle("ascuns");
    if (ascuns) {
        btn.textContent = "Afișează detalii";
        btn.setAttribute("aria-expanded", "false");
    } else {
        btn.textContent = "Ascunde detalii";
        btn.setAttribute("aria-expanded", "true");
    }
});
