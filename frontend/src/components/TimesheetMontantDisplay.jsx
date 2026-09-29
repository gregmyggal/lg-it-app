import { useState, useEffect } from 'react';
import client from '../api/client';

export default function TimesheetMontantDisplay({ timesheets, professeurId, year, month }) {
  const [montants, setMontants] = useState(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState(null);

  useEffect(() => {
    loadMontants();
  }, [professeurId, year, month]);

  async function loadMontants() {
    if (!professeurId || !year || !month) {
      setMontants(null);
      return;
    }

    try {
      setLoading(true);
      const response = await client.get('/timesheets/preview-pdf', {
        params: { professeur_id: professeurId, year, month }
      });
      setMontants(response.data);
      setError(null);
    } catch (err) {
      setError('Impossible de charger les montants');
      console.error(err);
    } finally {
      setLoading(false);
    }
  }

  if (loading) {
    return <div style={{ padding: '16px', color: '#999' }}>Calcul en cours…</div>;
  }

  if (error) {
    return <div style={{ padding: '16px', color: '#dc2626' }}>⚠️ {error}</div>;
  }

  if (!montants) {
    return null;
  }

  const { synthese, conformite } = montants;

  return (
    <div style={{
      background: '#f9fafb',
      padding: '20px',
      borderRadius: '8px',
      marginBottom: '20px',
      border: '1px solid #e5e7eb',
    }}>
      <h3 style={{ marginTop: 0, marginBottom: '15px', color: '#1f2937' }}>
        📊 Résumé du Mois
      </h3>

      <div style={{
        display: 'grid',
        gridTemplateColumns: 'repeat(auto-fit, minmax(200px, 1fr))',
        gap: '15px',
        marginBottom: '20px',
      }}>
        {/* Total heures */}
        <div style={{
          background: 'white',
          padding: '15px',
          borderRadius: '6px',
          borderLeft: '4px solid #3b82f6',
        }}>
          <div style={{ fontSize: '0.85em', color: '#6b7280', marginBottom: '5px' }}>
            Total heures
          </div>
          <div style={{ fontSize: '1.5em', fontWeight: 'bold', color: '#1f2937' }}>
            {synthese.total_heures}h
          </div>
        </div>

        {/* Total montant */}
        <div style={{
          background: 'white',
          padding: '15px',
          borderRadius: '6px',
          borderLeft: '4px solid #10b981',
        }}>
          <div style={{ fontSize: '0.85em', color: '#6b7280', marginBottom: '5px' }}>
            Total défraiement
          </div>
          <div style={{ fontSize: '1.5em', fontWeight: 'bold', color: '#1f2937' }}>
            {synthese.total_montant.toFixed(2)}€
          </div>
        </div>

        {/* Jours encodés */}
        <div style={{
          background: 'white',
          padding: '15px',
          borderRadius: '6px',
          borderLeft: '4px solid #f59e0b',
        }}>
          <div style={{ fontSize: '0.85em', color: '#6b7280', marginBottom: '5px' }}>
            Jours encodés
          </div>
          <div style={{ fontSize: '1.5em', fontWeight: 'bold', color: '#1f2937' }}>
            {synthese.nombre_jours_encodes}
          </div>
        </div>

        {/* Conformité */}
        <div style={{
          background: conformite.conforme ? '#d1fae5' : '#fee2e2',
          padding: '15px',
          borderRadius: '6px',
          borderLeft: `4px solid ${conformite.conforme ? '#10b981' : '#ef4444'}`,
        }}>
          <div style={{ fontSize: '0.85em', color: '#6b7280', marginBottom: '5px' }}>
            Statut conformité
          </div>
          <div style={{
            fontSize: '0.95em',
            fontWeight: 'bold',
            color: conformite.conforme ? '#065f46' : '#991b1b',
          }}>
            {conformite.conforme ? '✓ Conforme' : '❌ Dépassements'}
          </div>
        </div>
      </div>

      {/* Alertes dépassements */}
      {synthese.depassements && synthese.depassements.length > 0 && (
        <div style={{
          background: '#fee2e2',
          border: '1px solid #fca5a5',
          padding: '15px',
          borderRadius: '6px',
          marginBottom: '15px',
        }}>
          <div style={{ fontWeight: 'bold', color: '#991b1b', marginBottom: '10px' }}>
            ⚠️ {synthese.depassements.length} jour(s) en dépassement
          </div>
          <ul style={{ margin: 0, paddingLeft: '20px', color: '#991b1b' }}>
            {synthese.depassements.map((dep, idx) => (
              <li key={idx} style={{ marginBottom: '5px' }}>
                <strong>{dep.date}</strong>: {dep.montant_total.toFixed(2)}€
                (max: {dep.max_autorise}€, +{dep.depassement.toFixed(2)}€)
              </li>
            ))}
          </ul>
        </div>
      )}

      {/* Détails par jour */}
      <details style={{ marginTop: '15px' }}>
        <summary style={{
          cursor: 'pointer',
          fontWeight: '500',
          color: '#667eea',
          padding: '8px 0',
        }}>
          📅 Voir les détails par jour
        </summary>
        <div style={{
          marginTop: '12px',
          background: 'white',
          borderRadius: '6px',
          overflow: 'hidden',
        }}>
          <table style={{
            width: '100%',
            borderCollapse: 'collapse',
            fontSize: '0.9em',
          }}>
            <thead>
              <tr style={{ borderBottom: '2px solid #e5e7eb', background: '#f3f4f6' }}>
                <th style={{ padding: '12px', textAlign: 'left', fontWeight: '600' }}>Date</th>
                <th style={{ padding: '12px', textAlign: 'left', fontWeight: '600' }}>Activités</th>
                <th style={{ padding: '12px', textAlign: 'right', fontWeight: '600' }}>Montant</th>
              </tr>
            </thead>
            <tbody>
              {Object.entries(montants.heures_par_jour).map(([date, data]) => (
                <tr key={date} style={{ borderBottom: '1px solid #e5e7eb' }}>
                  <td style={{ padding: '12px' }}>
                    <strong>{new Date(date).toLocaleDateString('fr-FR')}</strong>
                  </td>
                  <td style={{ padding: '12px' }}>
                    {data.activites.map((act, i) => (
                      <div key={i} style={{ fontSize: '0.85em', color: '#666' }}>
                        {act.type}: {act.heures}h
                      </div>
                    ))}
                  </td>
                  <td style={{
                    padding: '12px',
                    textAlign: 'right',
                    fontWeight: 'bold',
                    color: data.montant_total > 44.02 ? '#dc2626' : '#1f2937',
                  }}>
                    {data.montant_total.toFixed(2)}€
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      </details>
    </div>
  );
}
