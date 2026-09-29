import { useState, useEffect } from 'react';
import client from '../api/client';
import { ADMIN_COLORS, ADMIN_SPACING } from '../styles/AdminDesignSystem';
import SessionCard from './SessionCard';
import SessionDetailModal from './SessionDetailModal';

const DAYS = ['Dim', 'Lun', 'Mar', 'Mer', 'Jeu', 'Ven', 'Sam'];
const MONTHS = [
  'Janvier', 'Février', 'Mars', 'Avril', 'Mai', 'Juin',
  'Juillet', 'Août', 'Septembre', 'Octobre', 'Novembre', 'Décembre'
];

export default function CalendarView({ year = 2026, month = 10, onSessionClick }) {
  const [sessions, setSessions] = useState([]);
  const [loading, setLoading] = useState(false);
  const [currentYear, setCurrentYear] = useState(year);
  const [currentMonth, setCurrentMonth] = useState(month);
  const [selectedSession, setSelectedSession] = useState(null);
  const [showModal, setShowModal] = useState(false);

  useEffect(() => {
    loadSessions();
  }, [currentYear, currentMonth]);

  const loadSessions = async () => {
    setLoading(true);
    try {
      const res = await client.get('/calendar/month', {
        params: {
          year: currentYear,
          month: currentMonth,
        },
      });
      setSessions(res.data.data || {});
    } catch (err) {
      console.error('Error loading sessions:', err);
    } finally {
      setLoading(false);
    }
  };

  const getDaysInMonth = (year, month) => {
    return new Date(year, month, 0).getDate();
  };

  const getFirstDayOfMonth = (year, month) => {
    return new Date(year, month - 1, 1).getDay();
  };

  const handlePrevMonth = () => {
    if (currentMonth === 1) {
      setCurrentMonth(12);
      setCurrentYear(currentYear - 1);
    } else {
      setCurrentMonth(currentMonth - 1);
    }
  };

  const handleNextMonth = () => {
    if (currentMonth === 12) {
      setCurrentMonth(1);
      setCurrentYear(currentYear + 1);
    } else {
      setCurrentMonth(currentMonth + 1);
    }
  };

  const handleSessionEdit = (session) => {
    setSelectedSession(session);
    setShowModal(true);
  };

  const handleSave = () => {
    loadSessions();
  };

  const daysInMonth = getDaysInMonth(currentYear, currentMonth);
  const firstDay = getFirstDayOfMonth(currentYear, currentMonth);
  const calendarDays = [];

  // Empty cells for days before month starts
  for (let i = 0; i < firstDay; i++) {
    calendarDays.push(null);
  }

  // Days of the month
  for (let day = 1; day <= daysInMonth; day++) {
    calendarDays.push(day);
  }

  return (
    <div style={{ background: 'white', borderRadius: '8px', overflow: 'hidden' }}>
      {/* Header */}
      <div
        style={{
          display: 'flex',
          justifyContent: 'space-between',
          alignItems: 'center',
          padding: ADMIN_SPACING.lg,
          background: '#f9fafb',
          borderBottom: `1px solid ${ADMIN_COLORS.border}`,
        }}
      >
        <button
          onClick={handlePrevMonth}
          style={{
            background: ADMIN_COLORS.primary,
            color: 'white',
            border: 'none',
            padding: `8px 12px`,
            borderRadius: '4px',
            cursor: 'pointer',
            fontSize: '14px',
            fontWeight: 600,
          }}
        >
          ◀ Mois précédent
        </button>
        <h2 style={{ margin: 0, fontSize: '18px', fontWeight: 700 }}>
          {MONTHS[currentMonth - 1]} {currentYear}
        </h2>
        <button
          onClick={handleNextMonth}
          style={{
            background: ADMIN_COLORS.primary,
            color: 'white',
            border: 'none',
            padding: `8px 12px`,
            borderRadius: '4px',
            cursor: 'pointer',
            fontSize: '14px',
            fontWeight: 600,
          }}
        >
          Mois suivant ▶
        </button>
      </div>

      {/* Day labels */}
      <div
        style={{
          display: 'grid',
          gridTemplateColumns: 'repeat(7, 1fr)',
          borderBottom: `1px solid ${ADMIN_COLORS.border}`,
        }}
      >
        {DAYS.map((day) => (
          <div
            key={day}
            style={{
              padding: ADMIN_SPACING.md,
              textAlign: 'center',
              fontWeight: 600,
              fontSize: '12px',
              color: '#6b7280',
              background: '#fafafa',
              borderRight: `1px solid ${ADMIN_COLORS.border}`,
            }}
          >
            {day}
          </div>
        ))}
      </div>

      {/* Calendar grid */}
      <div
        style={{
          display: 'grid',
          gridTemplateColumns: 'repeat(7, 1fr)',
          minHeight: '600px',
        }}
      >
        {calendarDays.map((day, idx) => (
          <div
            key={idx}
            style={{
              borderRight: `1px solid ${ADMIN_COLORS.border}`,
              borderBottom: `1px solid ${ADMIN_COLORS.border}`,
              padding: ADMIN_SPACING.sm,
              background: day ? 'white' : '#fafafa',
              minHeight: '120px',
              overflow: 'auto',
            }}
          >
            {day && (
              <div>
                <div
                  style={{
                    fontSize: '13px',
                    fontWeight: 600,
                    marginBottom: ADMIN_SPACING.sm,
                    color: ADMIN_COLORS.textPrimary,
                  }}
                >
                  {day}
                </div>

                {/* Sessions for this day */}
                {sessions[`${currentYear}-${String(currentMonth).padStart(2, '0')}-${String(day).padStart(2, '0')}`]?.map(
                  (session) => (
                    <div
                      key={session.id}
                      onClick={() => handleSessionEdit(session)}
                      style={{
                        background: '#f0f9ff',
                        border: `1px solid #bfdbfe`,
                        borderRadius: '4px',
                        padding: '4px 6px',
                        marginBottom: '4px',
                        fontSize: '11px',
                        fontWeight: 500,
                        color: '#0c4a6e',
                        cursor: 'pointer',
                        transition: 'all 0.2s',
                        whiteSpace: 'nowrap',
                        overflow: 'hidden',
                        textOverflow: 'ellipsis',
                      }}
                      onMouseEnter={(e) => {
                        e.currentTarget.style.background = '#0c4a6e';
                        e.currentTarget.style.color = 'white';
                      }}
                      onMouseLeave={(e) => {
                        e.currentTarget.style.background = '#f0f9ff';
                        e.currentTarget.style.color = '#0c4a6e';
                      }}
                      title={session.titre || session.cours?.titre}
                    >
                      {session.heure_debut?.substring(0, 5)} {session.titre || session.cours?.titre}
                    </div>
                  )
                )}
              </div>
            )}
          </div>
        ))}
      </div>

      {/* Session Detail Modal */}
      <SessionDetailModal
        session={selectedSession}
        isOpen={showModal}
        onClose={() => {
          setShowModal(false);
          setSelectedSession(null);
        }}
        onSave={handleSave}
      />
    </div>
  );
}
