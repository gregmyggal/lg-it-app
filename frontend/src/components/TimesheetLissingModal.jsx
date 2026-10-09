import { useState } from 'react';
import client from '../api/client';
import AdminButton from './AdminButton';
import { ADMIN_COLORS } from '../styles/AdminDesignSystem';

export default function TimesheetLissingModal({
  timesheetId,
  datePrestation,
  montantActuel,
  isOpen,
  onClose,
  onSuccess,
  year,
  month,
}) {
  const [loading, setLoading] = useState(false);
  const [proposal, setProposal] = useState(null);
  const [error, setError] = useState(null);
  const [montantToMove, setMontantToMove] = useState('');
  const [dateTarget, setDateTarget] = useState('');
  const [applying, setApplying] = useState(false);

  async function loadProposal() {
    try {
      setLoading(true);
      setError(null);
      const response = await client.get(`/timesheets/${timesheetId}/propose-lissage`, {
        params: { year, month }
      });
      setProposal(response.data);
      if (response.data.suggestion) {
        setDateTarget(response.data.suggestion.date_cible || '');
        setMontantToMove(response.data.suggestion.quantite_a_deplacer_eur.toString());
      }
    } catch (err) {
      setError('Impossible de charger la proposition de lissage');
      console.error(err);
    } finally {
      setLoading(false);
    }
  }

  async function applyLissage() {
    if (!dateTarget || !montantToMove) {
      setError('Veuillez remplir tous les champs');
      return;
    }

    try {
      setApplying(true);
      setError(null);
      await client.post(`/timesheets/${timesheetId}/apply-lissage`, {
        date_to: dateTarget,
        montant_to_move: parseFloat(montantToMove),
      });

      onSuccess();
      onClose();
    } catch (err) {
      setError(err.response?.data?.error || 'Erreur lors du lissage');
      console.error(err);
    } finally {
      setApplying(false);
    }
  }

  if (!isOpen) return null;

  return (
    <div className="adm-modal-overlay" style={{
      position: 'fixed',
      top: 0,
      left: 0,
      right: 0,
      bottom: 0,
      background: 'rgba(0,0,0,0.5)',
      display: 'flex',
      alignItems: 'center',
      justifyContent: 'center',
      zIndex: 1000,
    }}>
      <div className="adm-modal adm-modal--plain" style={{
        background: 'var(--c-card)',
        borderRadius: '8px',
        padding: '24px',
        maxWidth: '500px',
        width: '90%',
        boxShadow: '0 20px 25px rgba(0,0,0,0.15)',
      }}>
        <h2 style={{ margin: '0 0 16px 0', color: 'var(--c-text)' }}>
          🔄 Lissage des Heures
        </h2>

        <div style={{
          background: 'var(--tone-warning-bg)',
          border: '1px solid var(--tone-warning-bd)',
          padding: '12px',
          borderRadius: '6px',
          marginBottom: '20px',
          fontSize: '0.9em',
          color: 'var(--tone-warning-fg)',
        }}>
          <strong>Date en dépassement:</strong> {new Date(datePrestation).toLocaleDateString('fr-FR')}
          <br />
          <strong>Montant total:</strong> {montantActuel.toFixed(2)}€ (max: 44,02€)
        </div>

        {!proposal && (
          <AdminButton
            variant="primary"
            onClick={loadProposal}
            disabled={loading}
            style={{ width: '100%' }}
          >
            {loading ? 'Calcul en cours…' : 'Calculer la suggestion'}
          </AdminButton>
        )}

        {error && (
          <div style={{
            background: 'var(--tone-error-bg)',
            color: ADMIN_COLORS.error,
            padding: '12px',
            borderRadius: '6px',
            marginBottom: '16px',
            fontSize: '0.9em',
          }}>
            ⚠️ {error}
          </div>
        )}

        {proposal && (
          <div>
            {proposal.success ? (
              <div>
                <div style={{
                  background: 'var(--tone-success-bg)',
                  border: '1px solid var(--tone-success-bd)',
                  padding: '12px',
                  borderRadius: '6px',
                  marginBottom: '16px',
                  color: 'var(--tone-success-fg)',
                  fontSize: '0.9em',
                }}>
                  {proposal.suggestion.note}
                </div>

                <div style={{ marginBottom: '16px' }}>
                  <label style={{
                    display: 'block',
                    marginBottom: '6px',
                    fontWeight: '500',
                    color: 'var(--c-text-muted)',
                  }}>
                    Montant à déplacer (€)
                  </label>
                  <input
                    type="number"
                    step="0.01"
                    min="0"
                    max="44.02"
                    value={montantToMove}
                    onChange={(e) => setMontantToMove(e.target.value)}
                    style={{
                      width: '100%',
                      padding: '8px',
                      border: '1px solid var(--c-border)',
                      borderRadius: '6px',
                      fontSize: '1em',
                    }}
                  />
                </div>

                <div style={{ marginBottom: '20px' }}>
                  <label style={{
                    display: 'block',
                    marginBottom: '6px',
                    fontWeight: '500',
                    color: 'var(--c-text-muted)',
                  }}>
                    Date cible
                  </label>
                  <input
                    type="date"
                    value={dateTarget}
                    onChange={(e) => setDateTarget(e.target.value)}
                    style={{
                      width: '100%',
                      padding: '8px',
                      border: '1px solid var(--c-border)',
                      borderRadius: '6px',
                      fontSize: '1em',
                    }}
                  />
                </div>

                <div style={{ display: 'flex', gap: '12px' }}>
                  <AdminButton
                    variant="primary"
                    onClick={applyLissage}
                    disabled={applying}
                    style={{ flex: 1 }}
                  >
                    {applying ? 'Application…' : 'Appliquer'}
                  </AdminButton>
                  <AdminButton
                    variant="secondary"
                    onClick={onClose}
                    disabled={applying}
                    style={{ flex: 1 }}
                  >
                    Annuler
                  </AdminButton>
                </div>
              </div>
            ) : (
              <div style={{ color: ADMIN_COLORS.error }}>
                {proposal.error}
              </div>
            )}
          </div>
        )}
      </div>
    </div>
  );
}
