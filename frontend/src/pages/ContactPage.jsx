import { useState } from 'react';
import client from '../api/client';

export default function ContactPage() {
  const [form, setForm] = useState({
    nom: '',
    email: '',
    telephone: '',
    sujet: '',
    message: '',
  });
  const [error, setError] = useState(null);
  const [success, setSuccess] = useState(false);
  const [loading, setLoading] = useState(false);

  async function handleSubmit(e) {
    e.preventDefault();
    setError(null);
    setSuccess(false);
    setLoading(true);

    try {
      await client.post('/contact', form);
      setSuccess(true);
      setForm({ nom: '', email: '', telephone: '', sujet: '', message: '' });
    } catch (err) {
      setError('Erreur lors de l\'envoi. Veuillez réessayer.');
    } finally {
      setLoading(false);
    }
  }

  return (
    <div style={{ maxWidth: '1100px', margin: '0 auto', padding: '60px 24px' }}>
      <div style={{ maxWidth: '700px' }}>
        <h1 style={{ marginBottom: '16px' }}>Contactez-nous</h1>
        <p style={{ fontSize: '17px', color: 'var(--text-muted)', marginBottom: '48px', lineHeight: '1.6' }}>
          Vous avez une question sur nos cours, stages ou formations ? Une demande spécifique ? Remplissez le formulaire ci-dessous et notre équipe vous répondra sous 24h.
        </p>

        {success && (
          <div style={{ background: '#d4edda', border: '1px solid #c3e6cb', borderRadius: '8px', padding: '16px', marginBottom: '24px', color: '#155724' }}>
            ✓ Votre message a été envoyé avec succès. Nous vous recontacterons bientôt.
          </div>
        )}

        <form onSubmit={handleSubmit} style={{ display: 'grid', gap: '20px' }}>
          <label style={{ display: 'grid', gap: '6px' }}>
            <span style={{ fontWeight: '700', fontSize: '14px' }}>Nom</span>
            <input
              type="text"
              value={form.nom}
              onChange={(e) => setForm({ ...form, nom: e.target.value })}
              required
              style={{ padding: '10px 12px', border: '1px solid var(--border)', borderRadius: '6px', fontSize: '14px' }}
            />
          </label>

          <label style={{ display: 'grid', gap: '6px' }}>
            <span style={{ fontWeight: '700', fontSize: '14px' }}>Email</span>
            <input
              type="email"
              value={form.email}
              onChange={(e) => setForm({ ...form, email: e.target.value })}
              required
              style={{ padding: '10px 12px', border: '1px solid var(--border)', borderRadius: '6px', fontSize: '14px' }}
            />
          </label>

          <label style={{ display: 'grid', gap: '6px' }}>
            <span style={{ fontWeight: '700', fontSize: '14px' }}>Téléphone (optionnel)</span>
            <input
              type="tel"
              value={form.telephone}
              onChange={(e) => setForm({ ...form, telephone: e.target.value })}
              style={{ padding: '10px 12px', border: '1px solid var(--border)', borderRadius: '6px', fontSize: '14px' }}
            />
          </label>

          <label style={{ display: 'grid', gap: '6px' }}>
            <span style={{ fontWeight: '700', fontSize: '14px' }}>Sujet</span>
            <input
              type="text"
              value={form.sujet}
              onChange={(e) => setForm({ ...form, sujet: e.target.value })}
              required
              style={{ padding: '10px 12px', border: '1px solid var(--border)', borderRadius: '6px', fontSize: '14px' }}
            />
          </label>

          <label style={{ display: 'grid', gap: '6px' }}>
            <span style={{ fontWeight: '700', fontSize: '14px' }}>Message</span>
            <textarea
              value={form.message}
              onChange={(e) => setForm({ ...form, message: e.target.value })}
              required
              rows={6}
              style={{ padding: '10px 12px', border: '1px solid var(--border)', borderRadius: '6px', fontSize: '14px', fontFamily: 'inherit' }}
            />
          </label>

          {error && <p style={{ color: '#dc3545', fontSize: '14px' }}>⚠ {error}</p>}

          <button
            type="submit"
            disabled={loading}
            className="btn btn-primary"
            style={{ alignSelf: 'start', opacity: loading ? 0.6 : 1, cursor: loading ? 'not-allowed' : 'pointer' }}
          >
            {loading ? 'Envoi en cours…' : 'Envoyer le message'}
          </button>
        </form>

        <div style={{ marginTop: '60px', paddingTop: '40px', borderTop: '1px solid var(--border)' }}>
          <h2 style={{ marginBottom: '24px' }}>Autres moyens de nous contacter</h2>
          <div style={{ display: 'grid', gap: '16px' }}>
            <div>
              <h3 style={{ margin: '0 0 4px 0', fontSize: '16px' }}>📍 Adresse</h3>
              <p style={{ margin: 0, color: 'var(--text-muted)', fontSize: '14px' }}>Logiscool Pays Vert<br />Ath, Belgique</p>
            </div>
            <div>
              <h3 style={{ margin: '0 0 4px 0', fontSize: '16px' }}>📞 Téléphone</h3>
              <a href="tel:+32..." style={{ color: 'var(--accent)', textDecoration: 'none', fontSize: '14px' }}>Nous appeler</a>
            </div>
            <div>
              <h3 style={{ margin: '0 0 4px 0', fontSize: '16px' }}>📧 Email</h3>
              <a href="mailto:contact@logiscool.be" style={{ color: 'var(--accent)', textDecoration: 'none', fontSize: '14px' }}>contact@logiscool.be</a>
            </div>
          </div>
        </div>
      </div>
    </div>
  );
}
