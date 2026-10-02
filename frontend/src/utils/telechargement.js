/** Enregistre un Blob reçu de l'API sous le nom donné (téléchargement navigateur). */
export function enregistrerBlob(blob, nomFichier) {
  const url = URL.createObjectURL(blob);
  const a = document.createElement('a');
  a.href = url;
  a.download = nomFichier;
  a.click();
  URL.revokeObjectURL(url);
}

/** Ouvre un PDF (Blob) dans un nouvel onglet. */
export function ouvrirPdf(blob) {
  const url = URL.createObjectURL(new Blob([blob], { type: 'application/pdf' }));
  window.open(url, '_blank', 'noopener');
  setTimeout(() => URL.revokeObjectURL(url), 60000);
}
