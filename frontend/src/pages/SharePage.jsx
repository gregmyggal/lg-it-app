import { useState, useEffect } from 'react';
import { useParams } from 'react-router-dom';
import '../styles/SharePage.css';

export default function SharePage() {
  const { code } = useParams();
  const [content, setContent] = useState(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState(null);

  useEffect(() => {
    if (!code) {
      setError('Code d\'accès manquant');
      setLoading(false);
      return;
    }

    fetchContent();
  }, [code]);

  const fetchContent = async () => {
    try {
      setLoading(true);
      setError(null);

      const response = await fetch(`${import.meta.env.VITE_API_URL}/share/${code}`, {
        headers: {
          'X-Share-Code': code,
          'Content-Type': 'application/json',
        },
      });

      if (!response.ok) {
        if (response.status === 404) {
          throw new Error('Code d\'accès invalide ou expiré');
        } else if (response.status === 403) {
          throw new Error('Ce code d\'accès a atteint sa limite d\'utilisation');
        } else {
          throw new Error('Erreur lors du chargement du contenu');
        }
      }

      const data = await response.json();
      setContent(data);
    } catch (err) {
      setError(err.message);
    } finally {
      setLoading(false);
    }
  };

  if (loading) {
    return (
      <div className="share-page-container">
        <div className="loading">
          <p>Chargement du contenu...</p>
        </div>
      </div>
    );
  }

  if (error) {
    return (
      <div className="share-page-container">
        <div className="error-message">
          <h2>Erreur d'accès</h2>
          <p>{error}</p>
          <p className="hint">Vérifiez le code d'accès fourni par votre professeur.</p>
        </div>
      </div>
    );
  }

  if (!content) {
    return (
      <div className="share-page-container">
        <div className="error-message">
          <h2>Contenu non trouvé</h2>
          <p>Le contenu que vous cherchez n'existe pas.</p>
        </div>
      </div>
    );
  }

  const { type, content: data, ressources, types, dates } = content;

  return (
    <div className="share-page">
      <div className="share-page-header">
        <h1>{data.titre}</h1>
        <p className="share-page-type">{formatType(type)}</p>
      </div>

      <div className="share-page-content">
        {data.extrait && (
          <div className="extrait">
            <p>{data.extrait}</p>
          </div>
        )}

        {data.image_path && (
          <div className="image-container">
            <img src={data.image_path} alt={data.titre} />
          </div>
        )}

        {data.contenu && (
          <div className="contenu">
            <div dangerouslySetInnerHTML={{ __html: data.contenu }} />
          </div>
        )}

        {ressources && ressources.length > 0 && (
          <div className="ressources-section">
            <h2>Ressources</h2>
            <ul className="ressources-list">
              {ressources.map((res) => (
                <li key={res.id}>
                  <a href={res.url} target="_blank" rel="noopener noreferrer" className="resource-link">
                    {res.titre || 'Ressource'}
                  </a>
                  {res.description && <p className="resource-desc">{res.description}</p>}
                </li>
              ))}
            </ul>
          </div>
        )}

        {dates && dates.length > 0 && (
          <div className="dates-section">
            <h2>Dates de session</h2>
            <ul className="dates-list">
              {dates.map((date) => (
                <li key={date.id}>
                  {formatDate(date.date_session)}
                </li>
              ))}
            </ul>
          </div>
        )}

        {types && types.length > 0 && (
          <div className="types-section">
            <h2>Catégories</h2>
            <div className="types-list">
              {types.map((t) => (
                <span key={t.id} className="type-badge">
                  {t.nom}
                </span>
              ))}
            </div>
          </div>
        )}

        {data.url_logiscool && (
          <div className="cta-section">
            <a href={data.url_logiscool} target="_blank" rel="noopener noreferrer" className="cta-button">
              Accéder à la plateforme
            </a>
          </div>
        )}
      </div>

      <footer className="share-page-footer">
        <p>LG-IT • Portail d'accès aux ressources</p>
      </footer>
    </div>
  );
}

function formatType(type) {
  const types = {
    Cours: 'Cours',
    Stage: 'Stage',
    Formation: 'Formation',
    Anniversaire: 'Anniversaire / Fête',
  };
  return types[type] || type;
}

function formatDate(dateString) {
  return new Date(dateString).toLocaleDateString('fr-FR', {
    weekday: 'long',
    year: 'numeric',
    month: 'long',
    day: 'numeric',
  });
}
