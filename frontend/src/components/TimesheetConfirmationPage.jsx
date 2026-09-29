import { useState, useEffect } from 'react';
import client from '../api/client';
import AdminButton from './AdminButton';
import { AdminPageHeader, AdminPageContent, AdminBadge } from './AdminPageLayout';
import { ADMIN_COLORS } from '../styles/AdminDesignSystem';
import TimesheetMontantDisplay from './TimesheetMontantDisplay';

export default function TimesheetConfirmationPage({ professeurId, year, month }) {
  const [pdfData, setPdfData] = useState(null);
  const [canSign, setCanSign] = useState(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState(null);
  const [signing, setSigning] = useState(false);
  const [signed, setSigned] = useState(false);

  useEffect(() => {
    loadData();
  }, [professeurId, year, month]);

  async function loadData() {
    try {
      setLoading(true);
      const [pdfRes, signRes] = await Promise.all([
        client.get('/timesheets/preview-pdf', {
          params: { professeur_id: professeurId, year, month }
        }),
        client.get('/timesheets/can-sign-month', {
          params: { professeur_id: professeurId, year, month }
        }),
      ]);
      setPdfData(pdfRes.data);
      setCanSign(signRes.data);
      setError(null);
    } catch (err) {
      setError('Impossible de charger les données');
      console.error(err);
    } finally {
      setLoading(false);
    }
  }

  async function handleSign() {
    try {
      setSigning(true);
      await client.post('/timesheets/sign-month', {
        professeur_id: professeurId,
        year,
        month,
      });
      setSigned(true);
      setError(null);
    } catch (err) {
      setError(err.response?.data?.error || 'Erreur lors de la signature');
      console.error(err);
    } finally {
      setSigning(false);
    }
  }

  if (loading) {
    return (
      <AdminPageContent>
        <div style={{ textAlign: 'center', padding: '40px', color: '#6b7280' }}>
          Chargement…
        </div>
      </AdminPageContent>
    );
  }

  if (error && !signed) {
    return (
      <AdminPageContent>
        <div style={{
          background: '#fee2e2',
          color: ADMIN_COLORS.error,
          padding: '16px',
          borderRadius: '8px',
        }}>
          ⚠️ {error}
        </div>
      </AdminPageContent>
    );
  }

  if (signed) {
    return (
      <AdminPageContent>
        <div style={{
          background: '#d1fae5',
          color: '#065f46',
          padding: '20px',
          borderRadius: '8px',
          textAlign: 'center',
        }}>
          <div style={{ fontSize: '2em', marginBottom: '8px' }}>✓</div>
          <strong>Signature effectuée!</strong>
          <p style={{ margin: '8px 0 0 0', opacity: 0.9 }}>
            Vos heures ont été signées et sont prêtes pour la génération du PDF
          </p>
        </div>
      </AdminPageContent>
    );
  }

  return (
    <>
      <AdminPageHeader
        icon="✍️"
        title="Confirmation et Signature"
        description="Vérifiez vos heures et confirmez votre signature pour ce mois"
      />

      <AdminPageContent>
        {/* Affichage des montants */}
        <TimesheetMontantDisplay
          timesheets={[]}
          professeurId={professeurId}
          year={year}
          month={month}
        />

        {/* Status */}
        {canSign && !canSign.can_sign && (
          <div style={{
            background: '#fee2e2',
            border: '1px solid #fca5a5',
            padding: '16px',
            borderRadius: '8px',
            marginBottom: '20px',
            color: ADMIN_COLORS.error,
          }}>
            <strong>❌ Signature non possible:</strong>
            <ul style={{ margin: '8px 0 0 0', paddingLeft: '20px' }}>
              {canSign.errors.map((err, idx) => (
                <li key={idx}>{err}</li>
              ))}
            </ul>
          </div>
        )}

        {canSign && canSign.can_sign && (
          <>
            <div style={{
              background: '#f0fdf4',
              border: '1px solid #bbf7d0',
              padding: '16px',
              borderRadius: '8px',
              marginBottom: '20px',
            }}>
              <div style={{ color: '#065f46' }}>
                <strong>✓ Vous pouvez signer ce mois</strong>
                <p style={{ margin: '8px 0 0 0', fontSize: '0.95em' }}>
                  Toutes vos heures sont confirmées et sans dépassement.
                  Cliquez sur le bouton ci-dessous pour signer.
                </p>
              </div>
            </div>

            <AdminButton
              variant="success"
              icon="✍️"
              onClick={handleSign}
              disabled={signing}
              style={{
                width: '100%',
                padding: '16px',
                fontSize: '1.05em',
                fontWeight: '600',
              }}
            >
              {signing ? 'Signature en cours…' : 'Je confirme et signe'}
            </AdminButton>

            <div style={{
              marginTop: '20px',
              padding: '16px',
              background: '#f3f4f6',
              borderRadius: '6px',
              fontSize: '0.9em',
              color: '#6b7280',
            }}>
              <strong>ℹ️ Qu'est-ce que ça signifie?</strong>
              <p style={{ margin: '8px 0 0 0' }}>
                En signant, vous confirmez que les heures encodées pour ce mois sont exactes
                et complètes. Votre directeur pourra ensuite générer le PDF de défraiement.
              </p>
            </div>
          </>
        )}
      </AdminPageContent>
    </>
  );
}
