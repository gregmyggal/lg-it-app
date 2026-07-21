import { useEffect, useState } from 'react';
import client from '../api/client';
import { useAuth } from '../auth/AuthContext';
import AdminButton from '../components/AdminButton';
import { AdminFormField, AdminInput } from '../components/AdminFormField';
import {
  AdminPageHeader,
  AdminPageContent,
  AdminBadge,
} from '../components/AdminPageLayout';
import { ADMIN_COLORS } from '../styles/AdminDesignSystem';

const STATUT_LABELS = {
  brouillon: 'Brouillon',
  soumis: 'Soumis',
  valide: 'Validé',
};

const STATUT_COLORS = {
  brouillon: 'amber',
  soumis: 'blue',
  valide: 'green',
};

function NewTimesheetForm({ professeurId, onCreated }) {
  const [date, setDate] = useState('');
  const [heures, setHeures] = useState('');
  const [error, setError] = useState(null);
  const [isSubmitting, setIsSubmitting] = useState(false);

  async function handleSubmit(e) {
    e.preventDefault();
    setError(null);
    setIsSubmitting(true);
    try {
      const res = await client.post('/timesheets', {
        professeur_id: professeurId,
        date_prestation: date,
        nombre_heures: heures,
      });
      onCreated(res.data);
      setDate('');
      setHeures('');
    } catch {
      setError('Saisie invalide.');
    } finally {
      setIsSubmitting(false);
    }
  }

  return (
    <form onSubmit={handleSubmit} style={{
      display: 'flex',
      gap: '12px',
      padding: '16px',
      background: '#f9fafb',
      borderRadius: '8px',
      marginBottom: '24px',
    }}>
      <AdminFormField label="">
        <AdminInput
          type="date"
          value={date}
          onChange={(e) => setDate(e.target.value)}
          required
        />
      </AdminFormField>
      <AdminFormField label="">
        <AdminInput
          type="number"
          step="0.5"
          min="0.5"
          max="24"
          placeholder="Heures"
          value={heures}
          onChange={(e) => setHeures(e.target.value)}
          required
        />
      </AdminFormField>
      <div style={{ display: 'flex', alignItems: 'flex-end', gap: '12px' }}>
        <AdminButton
          variant="primary"
          icon="➕"
          onClick={handleSubmit}
          disabled={isSubmitting}
        >
          Ajouter
        </AdminButton>
      </div>
      {error && <span style={{ color: ADMIN_COLORS.error }}>{error}</span>}
    </form>
  );
}

export default function TimesheetsPage() {
  const { user } = useAuth();
  const [timesheets, setTimesheets] = useState(null);
  const [error, setError] = useState(null);
  const [success, setSuccess] = useState(null);
  const [isSubmitting, setIsSubmitting] = useState(false);

  const isStaff = user.role === 'admin' || user.role === 'directeur';

  useEffect(() => {
    client
      .get('/timesheets')
      .then((res) => setTimesheets(res.data))
      .catch(() => setError('Impossible de charger les timesheets.'));
  }, []);

  function updateLocal(id, patch) {
    setTimesheets((prev) => prev.map((t) => (t.id === id ? { ...t, ...patch } : t)));
  }

  async function handleSubmitEntry(id) {
    try {
      setIsSubmitting(true);
      const res = await client.post(`/timesheets/${id}/submit`);
      updateLocal(id, res.data);
      setSuccess('Timesheet soumis');
      setTimeout(() => setSuccess(null), 2000);
    } catch {
      setError('Erreur lors de la soumission');
    } finally {
      setIsSubmitting(false);
    }
  }

  async function handleValidate(id) {
    try {
      setIsSubmitting(true);
      const res = await client.post(`/timesheets/${id}/validate`);
      updateLocal(id, res.data);
      setSuccess('Timesheet validé');
      setTimeout(() => setSuccess(null), 2000);
    } catch {
      setError('Erreur lors de la validation');
    } finally {
      setIsSubmitting(false);
    }
  }

  if (error && !success) {
    return (
      <div style={{ padding: '24px' }}>
        <p style={{ color: ADMIN_COLORS.error }}>⚠️ {error}</p>
      </div>
    );
  }

  if (timesheets === null) {
    return (
      <div style={{ padding: '24px', textAlign: 'center', color: '#6b7280' }}>
        Chargement…
      </div>
    );
  }

  return (
    <>
      <AdminPageHeader
        icon="📋"
        title="Timesheets"
        description={isStaff ? 'Validez les timesheets de vos professeurs' : 'Gérez vos heures de prestation'}
        badge={`${timesheets.length} entrées`}
      />

      <AdminPageContent>
        {error && (
          <div style={{
            background: '#fee2e2',
            color: ADMIN_COLORS.error,
            padding: '16px',
            borderRadius: '8px',
            marginBottom: '16px',
            display: 'flex',
            gap: '12px',
          }}>
            <span>⚠️</span>
            <div>{error}</div>
          </div>
        )}

        {success && (
          <div style={{
            background: '#d1fae5',
            color: ADMIN_COLORS.success,
            padding: '16px',
            borderRadius: '8px',
            marginBottom: '16px',
            display: 'flex',
            gap: '12px',
          }}>
            <span>✓</span>
            <div>{success}</div>
          </div>
        )}

        {!isStaff && user.professeur && (
          <NewTimesheetForm
            professeurId={user.professeur.id}
            onCreated={(t) => setTimesheets((prev) => [t, ...prev])}
          />
        )}

        <div style={{
          background: 'white',
          borderRadius: '8px',
          border: `1px solid ${ADMIN_COLORS.border}`,
          overflow: 'hidden',
          boxShadow: '0 1px 3px rgba(0,0,0,0.1)',
        }}>
          <table style={{
            width: '100%',
            borderCollapse: 'collapse',
          }}>
            <thead>
              <tr style={{
                background: '#f9fafb',
                borderBottom: `1px solid ${ADMIN_COLORS.border}`,
              }}>
                {isStaff && (
                  <th style={{
                    padding: '16px',
                    textAlign: 'left',
                    fontSize: '12px',
                    fontWeight: 600,
                    color: ADMIN_COLORS.textPrimary,
                    textTransform: 'uppercase',
                    letterSpacing: '0.5px',
                  }}>
                    Professeur
                  </th>
                )}
                <th style={{
                  padding: '16px',
                  textAlign: 'left',
                  fontSize: '12px',
                  fontWeight: 600,
                  color: ADMIN_COLORS.textPrimary,
                  textTransform: 'uppercase',
                  letterSpacing: '0.5px',
                }}>
                  Date
                </th>
                <th style={{
                  padding: '16px',
                  textAlign: 'left',
                  fontSize: '12px',
                  fontWeight: 600,
                  color: ADMIN_COLORS.textPrimary,
                  textTransform: 'uppercase',
                  letterSpacing: '0.5px',
                }}>
                  Heures
                </th>
                <th style={{
                  padding: '16px',
                  textAlign: 'left',
                  fontSize: '12px',
                  fontWeight: 600,
                  color: ADMIN_COLORS.textPrimary,
                  textTransform: 'uppercase',
                  letterSpacing: '0.5px',
                }}>
                  Statut
                </th>
                <th style={{
                  padding: '16px',
                  textAlign: 'right',
                  fontSize: '12px',
                  fontWeight: 600,
                  color: ADMIN_COLORS.textPrimary,
                  textTransform: 'uppercase',
                  letterSpacing: '0.5px',
                }}>
                  Actions
                </th>
              </tr>
            </thead>
            <tbody>
              {timesheets.map((t, idx) => (
                <tr
                  key={t.id}
                  style={{
                    borderBottom: `1px solid ${ADMIN_COLORS.border}`,
                    background: idx % 2 === 0 ? 'white' : '#f9fafb',
                  }}
                >
                  {isStaff && (
                    <td style={{
                      padding: '16px',
                      fontSize: '14px',
                      color: ADMIN_COLORS.textPrimary,
                    }}>
                      {t.professeur?.prenom} {t.professeur?.nom}
                    </td>
                  )}
                  <td style={{
                    padding: '16px',
                    fontSize: '14px',
                    color: ADMIN_COLORS.textPrimary,
                  }}>
                    {t.date_prestation?.slice(0, 10)}
                  </td>
                  <td style={{
                    padding: '16px',
                    fontSize: '14px',
                    color: ADMIN_COLORS.textPrimary,
                  }}>
                    {t.nombre_heures}h
                  </td>
                  <td style={{
                    padding: '16px',
                    fontSize: '14px',
                  }}>
                    <AdminBadge
                      label={STATUT_LABELS[t.statut_validation]}
                      color={STATUT_COLORS[t.statut_validation]}
                    />
                  </td>
                  <td style={{
                    padding: '16px',
                    textAlign: 'right',
                  }}>
                    {!isStaff && t.statut_validation === 'brouillon' && (
                      <AdminButton
                        variant="primary"
                        size="sm"
                        icon="✓"
                        onClick={() => handleSubmitEntry(t.id)}
                        disabled={isSubmitting}
                      >
                        Soumettre
                      </AdminButton>
                    )}
                    {isStaff && t.statut_validation === 'soumis' && (
                      <AdminButton
                        variant="success"
                        size="sm"
                        icon="✓"
                        onClick={() => handleValidate(t.id)}
                        disabled={isSubmitting}
                      >
                        Valider
                      </AdminButton>
                    )}
                    {(
                      (t.statut_validation === 'soumis' && !isStaff) ||
                      (t.statut_validation === 'valide')
                    ) && (
                      <span style={{ fontSize: '13px', color: '#9ca3af' }}>—</span>
                    )}
                  </td>
                </tr>
              ))}
            </tbody>
          </table>

          {timesheets.length === 0 && (
            <div style={{
              padding: '40px',
              textAlign: 'center',
              color: '#9ca3af',
            }}>
              Aucun timesheet
            </div>
          )}
        </div>
      </AdminPageContent>
    </>
  );
}
