import { useState } from 'react';
import { getSchema, validateValue } from '../utils/SchemaRegistry';

export default function ListItemEditor({ sectionKey, value, onChange, label }) {
  const schema = getSchema(sectionKey);
  const [editingIndex, setEditingIndex] = useState(null);
  const [newItem, setNewItem] = useState('');
  const [error, setError] = useState(null);

  if (!schema) return null;

  const isSimpleList = schema.type === 'list';
  const isListOfObjects = schema.type === 'list-of-objects';
  const isObject = schema.type === 'object';
  const isObjectWithList = schema.type === 'object-with-list';
  const isString = schema.type === 'string';

  // Simple list management
  function addListItem() {
    if (!newItem.trim()) {
      setError('Veuillez entrer une valeur');
      return;
    }
    if (schema.validation?.minLength && newItem.length < schema.validation.minLength) {
      setError(`Minimum ${schema.validation.minLength} caractères`);
      return;
    }
    const currentList = Array.isArray(value) ? value : [];
    onChange([...currentList, newItem.trim()]);
    setNewItem('');
    setError(null);
  }

  function removeListItem(index) {
    const currentList = Array.isArray(value) ? value : [];
    onChange(currentList.filter((_, i) => i !== index));
    setEditingIndex(null);
  }

  function updateListItem(index, newValue) {
    const currentList = Array.isArray(value) ? value : [];
    const updated = [...currentList];
    updated[index] = newValue;
    onChange(updated);
    setEditingIndex(null);
  }

  // Object management
  function updateObjectField(key, newValue) {
    const currentObj = value && typeof value === 'object' && !Array.isArray(value) ? value : {};
    onChange({ ...currentObj, [key]: newValue });
  }

  // List of objects management
  function addObjectItem() {
    const currentList = Array.isArray(value) ? value : [];
    const emptyItem = {};
    schema.fields?.forEach((field) => {
      emptyItem[field.key] = '';
    });
    onChange([...currentList, emptyItem]);
  }

  function updateObjectItemField(index, fieldKey, newValue) {
    const currentList = Array.isArray(value) ? value : [];
    const updated = [...currentList];
    updated[index][fieldKey] = newValue;
    onChange(updated);
  }

  function removeObjectItem(index) {
    const currentList = Array.isArray(value) ? value : [];
    onChange(currentList.filter((_, i) => i !== index));
  }

  // Object with list management (like section_pourqui)
  function updateObjectWithListField(key, newValue) {
    const currentObj = value && typeof value === 'object' && !Array.isArray(value) ? value : {};
    onChange({ ...currentObj, [key]: newValue });
  }

  // ==================== RENDERERS ====================

  if (isString) {
    return (
      <div style={{ display: 'flex', flexDirection: 'column', gap: '12px' }}>
        <label style={{ fontSize: '14px', fontWeight: 600, color: '#111827' }}>
          {label}
        </label>
        <textarea
          value={value || ''}
          onChange={(e) => onChange(e.target.value)}
          placeholder={schema.placeholder}
          style={{
            width: '100%',
            padding: '12px 16px',
            border: '1px solid #e5e7eb',
            borderRadius: '8px',
            fontFamily: 'inherit',
            fontSize: '14px',
            lineHeight: '1.6',
            resize: 'vertical',
            minHeight: '120px',
            color: '#111827',
            transition: 'border-color 0.2s ease',
          }}
          onFocus={(e) => {
            e.target.style.borderColor = '#2563eb';
            e.target.style.boxShadow = '0 0 0 3px rgba(37, 99, 235, 0.1)';
          }}
          onBlur={(e) => {
            e.target.style.borderColor = '#e5e7eb';
            e.target.style.boxShadow = 'none';
          }}
        />
      </div>
    );
  }

  if (isSimpleList) {
    return (
      <div style={{ display: 'flex', flexDirection: 'column', gap: '16px' }}>
        <label style={{ fontSize: '14px', fontWeight: 600, color: '#111827' }}>
          {schema.icon} {label}
        </label>

        <div style={{ display: 'flex', flexDirection: 'column', gap: '8px' }}>
          {Array.isArray(value) &&
            value.map((item, index) => (
              <div
                key={index}
                style={{
                  display: 'flex',
                  alignItems: 'center',
                  gap: '8px',
                  padding: '12px 16px',
                  background: '#f9fafb',
                  border: '1px solid #e5e7eb',
                  borderRadius: '8px',
                  transition: 'all 0.2s ease',
                }}
                onMouseEnter={(e) => {
                  e.currentTarget.style.background = '#f3f4f6';
                  e.currentTarget.style.borderColor = '#d1d5db';
                }}
                onMouseLeave={(e) => {
                  e.currentTarget.style.background = '#f9fafb';
                  e.currentTarget.style.borderColor = '#e5e7eb';
                }}
              >
                {editingIndex === index ? (
                  <>
                    <input
                      autoFocus
                      type="text"
                      value={item}
                      onChange={(e) => updateListItem(index, e.target.value)}
                      onKeyPress={(e) => {
                        if (e.key === 'Enter') setEditingIndex(null);
                      }}
                      onBlur={() => setEditingIndex(null)}
                      style={{
                        flex: 1,
                        padding: '8px 12px',
                        border: '1px solid #2563eb',
                        borderRadius: '6px',
                        fontSize: '14px',
                        outline: 'none',
                      }}
                    />
                  </>
                ) : (
                  <>
                    <span style={{ flex: 1, fontSize: '14px', color: '#111827' }}>{item}</span>
                    <button
                      type="button"
                      onClick={() => setEditingIndex(index)}
                      style={{
                        padding: '4px 8px',
                        background: 'transparent',
                        border: 'none',
                        color: '#6b7280',
                        cursor: 'pointer',
                        fontSize: '14px',
                        transition: 'color 0.2s ease',
                      }}
                      onMouseEnter={(e) => {
                        e.target.style.color = '#2563eb';
                      }}
                      onMouseLeave={(e) => {
                        e.target.style.color = '#6b7280';
                      }}
                    >
                      ✎
                    </button>
                    <button
                      type="button"
                      onClick={() => removeListItem(index)}
                      style={{
                        padding: '4px 8px',
                        background: 'transparent',
                        border: 'none',
                        color: '#6b7280',
                        cursor: 'pointer',
                        fontSize: '14px',
                        transition: 'color 0.2s ease',
                      }}
                      onMouseEnter={(e) => {
                        e.target.style.color = '#ef4444';
                      }}
                      onMouseLeave={(e) => {
                        e.target.style.color = '#6b7280';
                      }}
                    >
                      ✕
                    </button>
                  </>
                )}
              </div>
            ))}
        </div>

        <div style={{ display: 'flex', gap: '8px' }}>
          <input
            type="text"
            value={newItem}
            onChange={(e) => {
              setNewItem(e.target.value);
              setError(null);
            }}
            onKeyPress={(e) => {
              if (e.key === 'Enter') addListItem();
            }}
            placeholder={schema.placeholder}
            style={{
              flex: 1,
              padding: '10px 12px',
              border: '1px solid #e5e7eb',
              borderRadius: '8px',
              fontSize: '14px',
              transition: 'border-color 0.2s ease',
              outline: 'none',
            }}
            onFocus={(e) => {
              e.target.style.borderColor = '#2563eb';
              e.target.style.boxShadow = '0 0 0 3px rgba(37, 99, 235, 0.1)';
            }}
            onBlur={(e) => {
              e.target.style.borderColor = '#e5e7eb';
              e.target.style.boxShadow = 'none';
            }}
          />
          <button
            type="button"
            onClick={addListItem}
            style={{
              padding: '10px 16px',
              background: '#2563eb',
              color: 'white',
              border: 'none',
              borderRadius: '8px',
              fontWeight: 600,
              cursor: 'pointer',
              fontSize: '14px',
              transition: 'all 0.2s ease',
            }}
            onMouseEnter={(e) => {
              e.target.style.background = '#1d4ed8';
            }}
            onMouseLeave={(e) => {
              e.target.style.background = '#2563eb';
            }}
          >
            + Ajouter
          </button>
        </div>

        {error && (
          <div style={{ fontSize: '13px', color: '#991b1b', background: '#fef2f2', padding: '8px 12px', borderRadius: '6px' }}>
            ⚠️ {error}
          </div>
        )}
      </div>
    );
  }

  if (isListOfObjects) {
    return (
      <div style={{ display: 'flex', flexDirection: 'column', gap: '16px' }}>
        <label style={{ fontSize: '14px', fontWeight: 600, color: '#111827' }}>
          {schema.icon} {label}
        </label>

        <div style={{ display: 'flex', flexDirection: 'column', gap: '12px' }}>
          {Array.isArray(value) &&
            value.map((item, index) => (
              <div
                key={index}
                style={{
                  padding: '16px',
                  background: 'white',
                  border: '1px solid #e5e7eb',
                  borderRadius: '8px',
                  transition: 'all 0.2s ease',
                }}
                onMouseEnter={(e) => {
                  e.currentTarget.style.boxShadow = '0 4px 6px rgba(0,0,0,0.07)';
                }}
                onMouseLeave={(e) => {
                  e.currentTarget.style.boxShadow = 'none';
                }}
              >
                <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'flex-start', gap: '12px' }}>
                  <div style={{ flex: 1, display: 'flex', flexDirection: 'column', gap: '12px' }}>
                    {schema.fields?.map((field) => (
                      <div key={field.key}>
                        <label style={{ fontSize: '12px', fontWeight: 600, color: '#6b7280', display: 'block', marginBottom: '4px' }}>
                          {field.label}
                        </label>
                        {field.type === 'textarea' ? (
                          <textarea
                            value={item[field.key] || ''}
                            onChange={(e) => updateObjectItemField(index, field.key, e.target.value)}
                            placeholder={field.placeholder}
                            style={{
                              width: '100%',
                              padding: '8px 12px',
                              border: '1px solid #e5e7eb',
                              borderRadius: '6px',
                              fontFamily: 'inherit',
                              fontSize: '14px',
                              lineHeight: '1.5',
                              resize: 'vertical',
                              minHeight: '80px',
                              color: '#111827',
                              transition: 'border-color 0.2s ease',
                            }}
                            onFocus={(e) => {
                              e.target.style.borderColor = '#2563eb';
                            }}
                            onBlur={(e) => {
                              e.target.style.borderColor = '#e5e7eb';
                            }}
                          />
                        ) : (
                          <input
                            type="text"
                            value={item[field.key] || ''}
                            onChange={(e) => updateObjectItemField(index, field.key, e.target.value)}
                            placeholder={field.placeholder}
                            style={{
                              width: '100%',
                              padding: '8px 12px',
                              border: '1px solid #e5e7eb',
                              borderRadius: '6px',
                              fontSize: '14px',
                              color: '#111827',
                              transition: 'border-color 0.2s ease',
                              outline: 'none',
                            }}
                            onFocus={(e) => {
                              e.target.style.borderColor = '#2563eb';
                            }}
                            onBlur={(e) => {
                              e.target.style.borderColor = '#e5e7eb';
                            }}
                          />
                        )}
                      </div>
                    ))}
                  </div>
                  <button
                    type="button"
                    onClick={() => removeObjectItem(index)}
                    style={{
                      padding: '6px 10px',
                      background: '#fef2f2',
                      color: '#ef4444',
                      border: '1px solid #fecaca',
                      borderRadius: '6px',
                      cursor: 'pointer',
                      fontSize: '14px',
                      fontWeight: 600,
                      transition: 'all 0.2s ease',
                    }}
                    onMouseEnter={(e) => {
                      e.target.style.background = '#fee2e2';
                      e.target.style.borderColor = '#fca5a5';
                    }}
                    onMouseLeave={(e) => {
                      e.target.style.background = '#fef2f2';
                      e.target.style.borderColor = '#fecaca';
                    }}
                  >
                    ✕ Supprimer
                  </button>
                </div>
              </div>
            ))}
        </div>

        <button
          type="button"
          onClick={addObjectItem}
          style={{
            padding: '12px 16px',
            background: '#f0f9ff',
            color: '#2563eb',
            border: '1px dashed #bfdbfe',
            borderRadius: '8px',
            fontWeight: 600,
            cursor: 'pointer',
            fontSize: '14px',
            transition: 'all 0.2s ease',
          }}
          onMouseEnter={(e) => {
            e.target.style.background = '#e0f2fe';
            e.target.style.borderColor = '#7dd3fc';
          }}
          onMouseLeave={(e) => {
            e.target.style.background = '#f0f9ff';
            e.target.style.borderColor = '#bfdbfe';
          }}
        >
          + Ajouter un élément
        </button>
      </div>
    );
  }

  if (isObject) {
    return (
      <div style={{ display: 'flex', flexDirection: 'column', gap: '14px' }}>
        <label style={{ fontSize: '14px', fontWeight: 600, color: '#111827' }}>
          {schema.icon} {label}
        </label>

        {schema.fields?.map((field) => (
          <div key={field.key}>
            <label style={{ fontSize: '12px', fontWeight: 600, color: '#6b7280', display: 'block', marginBottom: '6px' }}>
              {field.label}
            </label>
            <input
              type={field.type || 'text'}
              value={value?.[field.key] || ''}
              onChange={(e) => updateObjectField(field.key, e.target.value)}
              placeholder={field.placeholder}
              style={{
                width: '100%',
                padding: '10px 12px',
                border: '1px solid #e5e7eb',
                borderRadius: '8px',
                fontSize: '14px',
                color: '#111827',
                transition: 'border-color 0.2s ease',
                outline: 'none',
              }}
              onFocus={(e) => {
                e.target.style.borderColor = '#2563eb';
                e.target.style.boxShadow = '0 0 0 3px rgba(37, 99, 235, 0.1)';
              }}
              onBlur={(e) => {
                e.target.style.borderColor = '#e5e7eb';
                e.target.style.boxShadow = 'none';
              }}
            />
          </div>
        ))}
      </div>
    );
  }

  if (isObjectWithList) {
    return (
      <div style={{ display: 'flex', flexDirection: 'column', gap: '16px' }}>
        <label style={{ fontSize: '14px', fontWeight: 600, color: '#111827' }}>
          {schema.icon} {label}
        </label>

        {schema.fields?.map((field) => {
          if (field.type === 'list') {
            return (
              <div key={field.key} style={{ display: 'flex', flexDirection: 'column', gap: '8px' }}>
                <label style={{ fontSize: '12px', fontWeight: 600, color: '#6b7280' }}>
                  {field.label}
                </label>

                <div style={{ display: 'flex', flexDirection: 'column', gap: '6px' }}>
                  {Array.isArray(value?.[field.key]) &&
                    value[field.key].map((item, index) => (
                      <div
                        key={index}
                        style={{
                          display: 'flex',
                          alignItems: 'center',
                          gap: '8px',
                          padding: '8px 12px',
                          background: '#f9fafb',
                          border: '1px solid #e5e7eb',
                          borderRadius: '6px',
                        }}
                      >
                        <span style={{ flex: 1, fontSize: '14px', color: '#111827' }}>{item}</span>
                        <button
                          type="button"
                          onClick={() => {
                            const updated = [...(value[field.key] || [])];
                            updated.splice(index, 1);
                            updateObjectWithListField(field.key, updated);
                          }}
                          style={{
                            padding: '2px 6px',
                            background: 'transparent',
                            border: 'none',
                            color: '#6b7280',
                            cursor: 'pointer',
                            fontSize: '12px',
                          }}
                        >
                          ✕
                        </button>
                      </div>
                    ))}
                </div>

                <div style={{ display: 'flex', gap: '6px' }}>
                  <input
                    type="text"
                    placeholder={field.placeholder}
                    onKeyPress={(e) => {
                      if (e.key === 'Enter' && e.target.value.trim()) {
                        const updated = [...(value?.[field.key] || []), e.target.value.trim()];
                        updateObjectWithListField(field.key, updated);
                        e.target.value = '';
                      }
                    }}
                    style={{
                      flex: 1,
                      padding: '8px 10px',
                      border: '1px solid #e5e7eb',
                      borderRadius: '6px',
                      fontSize: '13px',
                      outline: 'none',
                      transition: 'border-color 0.2s ease',
                    }}
                    onFocus={(e) => {
                      e.target.style.borderColor = '#2563eb';
                    }}
                    onBlur={(e) => {
                      e.target.style.borderColor = '#e5e7eb';
                    }}
                  />
                  <button
                    type="button"
                    onClick={(e) => {
                      const input = e.target.previousElementSibling;
                      if (input.value.trim()) {
                        const updated = [...(value?.[field.key] || []), input.value.trim()];
                        updateObjectWithListField(field.key, updated);
                        input.value = '';
                      }
                    }}
                    style={{
                      padding: '8px 12px',
                      background: '#f3f4f6',
                      border: '1px solid #e5e7eb',
                      borderRadius: '6px',
                      cursor: 'pointer',
                      fontSize: '12px',
                      fontWeight: 600,
                      color: '#374151',
                      transition: 'all 0.2s ease',
                    }}
                    onMouseEnter={(e) => {
                      e.target.style.background = '#e5e7eb';
                    }}
                    onMouseLeave={(e) => {
                      e.target.style.background = '#f3f4f6';
                    }}
                  >
                    +
                  </button>
                </div>
              </div>
            );
          }

          return (
            <div key={field.key}>
              <label style={{ fontSize: '12px', fontWeight: 600, color: '#6b7280', display: 'block', marginBottom: '6px' }}>
                {field.label}
              </label>
              {field.type === 'textarea' ? (
                <textarea
                  value={value?.[field.key] || ''}
                  onChange={(e) => updateObjectWithListField(field.key, e.target.value)}
                  placeholder={field.placeholder}
                  style={{
                    width: '100%',
                    padding: '10px 12px',
                    border: '1px solid #e5e7eb',
                    borderRadius: '8px',
                    fontFamily: 'inherit',
                    fontSize: '14px',
                    lineHeight: '1.5',
                    resize: 'vertical',
                    minHeight: '100px',
                    color: '#111827',
                    transition: 'border-color 0.2s ease',
                    outline: 'none',
                  }}
                  onFocus={(e) => {
                    e.target.style.borderColor = '#2563eb';
                  }}
                  onBlur={(e) => {
                    e.target.style.borderColor = '#e5e7eb';
                  }}
                />
              ) : (
                <input
                  type="text"
                  value={value?.[field.key] || ''}
                  onChange={(e) => updateObjectWithListField(field.key, e.target.value)}
                  placeholder={field.placeholder}
                  style={{
                    width: '100%',
                    padding: '10px 12px',
                    border: '1px solid #e5e7eb',
                    borderRadius: '8px',
                    fontSize: '14px',
                    color: '#111827',
                    transition: 'border-color 0.2s ease',
                    outline: 'none',
                  }}
                  onFocus={(e) => {
                    e.target.style.borderColor = '#2563eb';
                  }}
                  onBlur={(e) => {
                    e.target.style.borderColor = '#e5e7eb';
                  }}
                />
              )}
            </div>
          );
        })}
      </div>
    );
  }

  return null;
}
