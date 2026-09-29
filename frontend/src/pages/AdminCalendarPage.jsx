import { useState } from 'react';
import CalendarView from '../components/CalendarView';
import { AdminPageHeader, AdminPageContent } from '../components/AdminPageLayout';

export default function AdminCalendarPage() {
  const [year, setYear] = useState(2026);
  const [month, setMonth] = useState(new Date().getMonth() + 1);

  return (
    <>
      <AdminPageHeader
        icon="📅"
        title="Calendrier des sessions"
        description="Consultez et gérez tous les sessions programmées"
        badge={`${month}/${year}`}
      />

      <AdminPageContent>
        <CalendarView year={year} month={month} />

        <div
          style={{
            marginTop: '24px',
            padding: '20px',
            background: '#f0f9ff',
            borderRadius: '8px',
            border: '1px solid #bfdbfe',
            fontSize: '13px',
            color: '#0c4a6e',
          }}
        >
          <div style={{ fontWeight: 600, marginBottom: '8px' }}>💡 Conseils</div>
          <ul style={{ margin: '0', paddingLeft: '20px' }}>
            <li>Cliquez sur une session pour la voir ou l'éditer</li>
            <li>Utilisez les boutons pour naviguer entre les mois</li>
            <li>Chaque session affiche l'horaire et le titre abrégé</li>
            <li>Les couleurs indiquent le statut de la session</li>
          </ul>
        </div>
      </AdminPageContent>
    </>
  );
}
