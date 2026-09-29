import { useState } from 'react';
import client from '../api/client';
import AdminButton from './AdminButton';
import { ADMIN_COLORS } from '../styles/AdminDesignSystem';

export default function TimesheetPdfGenerator({
  professeurId,
  year,
  month,
  professeurName,
  onGenerateSuccess,
}) {
  const [generating, setGenerating] = useState(false);
  const [downloading, setDownloading] = useState(false);
  const [error, setError] = useState(null);
  const [pdfGenerated, setPdfGenerated] = useState(false);

  async function handleGeneratePdf() {
    const confirmed = window.confirm(
      'Êtes-vous sûr de vouloir générer le PDF?\n\n' +
      'Tous les timesheets signés seront marqués comme "généré".\n' +
      'Cette action ne peut pas être annulée.'
    );

    if (!confirmed) return;

    try {
      setGenerating(true);
      setError(null);

      const response = await client.post('/timesheets/generate-pdf', {
        professeur_id: professeurId,
        year,
        month,
      });

      if (response.data.success) {
        setPdfGenerated(true);
        if (onGenerateSuccess) {
          onGenerateSuccess();
        }
      } else {
        setError(response.data.error || 'Erreur lors de la génération');
      }
    } catch (err) {
      setError(err.response?.data?.error || 'Erreur lors de la génération du PDF');
      console.error(err);
    } finally {
      setGenerating(false);
    }
  }

  async function handleDownloadPdf() {
    try {
      setDownloading(true);
      setError(null);

      const response = await client.get('/timesheets/download-pdf', {
        params: {
          professeur_id: professeurId,
          year,
          month,
        },
        responseType: 'blob',
      });

      const url = window.URL.createObjectURL(new Blob([response.data]));
      const link = document.createElement('a');
      link.href = url;
      link.setAttribute(
        'download',
        `defraiement_${professeurName}_${year}-${month}.pdf`
      );
      document.body.appendChild(link);
      link.click();
      document.body.removeChild(link);
      window.URL.revokeObjectURL(url);
    } catch (err) {
      setError(err.response?.data?.error || 'Erreur lors du téléchargement');
      console.error(err);
    } finally {
      setDownloading(false);
    }
  }

  return (
    <div style={{
      background: '#f9fafb',
      border: `1px solid ${ADMIN_COLORS.border}`,
      padding: '20px',
      borderRadius: '8px',
      marginTop: '24px',
    }}>
      <h3 style={{
        margin: '0 0 16px 0',
        color: '#1f2937',
        fontSize: '1.1em',
      }}>
        📄 Génération du Défraiement
      </h3>

      <p style={{
        margin: '0 0 16px 0',
        color: '#6b7280',
        fontSize: '0.95em',
      }}>
        Générez le PDF de défraiement une fois que tous les timesheets sont signés.
        Une fois généré, vous pourrez le télécharger et l'archiver.
      </p>

      {error && (
        <div style={{
          background: '#fee2e2',
          color: ADMIN_COLORS.error,
          padding: '12px',
          borderRadius: '6px',
          marginBottom: '16px',
          fontSize: '0.9em',
        }}>
          ⚠️ {error}
        </div>
      )}

      <div style={{
        display: 'flex',
        gap: '12px',
        flexWrap: 'wrap',
      }}>
        {!pdfGenerated && (
          <AdminButton
            variant="primary"
            icon="⚙️"
            onClick={handleGeneratePdf}
            disabled={generating}
          >
            {generating ? 'Génération…' : 'Générer le PDF'}
          </AdminButton>
        )}

        {pdfGenerated && (
          <>
            <div style={{
              background: '#f0fdf4',
              color: '#065f46',
              padding: '12px 16px',
              borderRadius: '6px',
              display: 'flex',
              alignItems: 'center',
              gap: '8px',
              fontSize: '0.9em',
              fontWeight: '500',
            }}>
              ✓ PDF généré avec succès
            </div>

            <AdminButton
              variant="success"
              icon="⬇️"
              onClick={handleDownloadPdf}
              disabled={downloading}
            >
              {downloading ? 'Téléchargement…' : 'Télécharger le PDF'}
            </AdminButton>
          </>
        )}
      </div>
    </div>
  );
}
