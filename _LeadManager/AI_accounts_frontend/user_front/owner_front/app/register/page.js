'use client';

import { useState, useEffect } from 'react';
import { useRouter } from 'next/navigation';

const API_URL = process.env.NEXT_PUBLIC_API_URL;

export default function ProviderRegisterPage() {
  const router = useRouter();

  const [step, setStep] = useState(1); // 1 = personal details, 2 = services

  const [categories, setCategories] = useState([]);
  const [categoriesLoading, setCategoriesLoading] = useState(true);

  const [form, setForm] = useState({
    name: '',
    email: '',
    phone: '',
    password: '',
    confirmPassword: '',
    bio: '',
  });

  // { [categoryId]: { selected, price, price_type } }
  const [serviceMap, setServiceMap] = useState({});
  // custom category name inputs: [{ tempId, name, price, price_type }]
  const [customServices, setCustomServices] = useState([]);

  const [location, setLocation] = useState({ latitude: null, longitude: null });
  const [locationStatus, setLocationStatus] = useState('idle');

  const [error, setError] = useState('');
  const [loading, setLoading] = useState(false);

  useEffect(() => {
    const fetchCategories = async () => {
      try {
        const res = await fetch(`${API_URL}/api_get_categories.php`);
        const json = await res.json();
        if (json.status === 'ok') {
          setCategories(json.categories);
        }
      } catch (err) {
        console.error('Failed to load categories', err);
      } finally {
        setCategoriesLoading(false);
      }
    };
    fetchCategories();
  }, []);

  const handleChange = (e) => {
    setForm({ ...form, [e.target.name]: e.target.value });
  };

  const toggleCategory = (catId) => {
    setServiceMap((prev) => {
      const existing = prev[catId];
      if (existing?.selected) {
        return { ...prev, [catId]: { ...existing, selected: false } };
      }
      return {
        ...prev,
        [catId]: {
          selected: true,
          price: existing?.price ?? '',
          price_type: existing?.price_type ?? 'fixed',
        },
      };
    });
  };

  const updateServiceField = (catId, field, value) => {
    setServiceMap((prev) => ({
      ...prev,
      [catId]: { ...prev[catId], [field]: value },
    }));
  };

  const addCustomService = () => {
    setCustomServices((prev) => [
      ...prev,
      { tempId: Date.now(), name: '', price: '', price_type: 'fixed' },
    ]);
  };

  const updateCustomService = (tempId, field, value) => {
    setCustomServices((prev) =>
      prev.map((c) => (c.tempId === tempId ? { ...c, [field]: value } : c))
    );
  };

  const removeCustomService = (tempId) => {
    setCustomServices((prev) => prev.filter((c) => c.tempId !== tempId));
  };

  const detectLocation = () => {
    if (!navigator.geolocation) {
      setLocationStatus('error');
      return;
    }
    setLocationStatus('loading');
    navigator.geolocation.getCurrentPosition(
      (pos) => {
        setLocation({
          latitude: pos.coords.latitude,
          longitude: pos.coords.longitude,
        });
        setLocationStatus('done');
      },
      () => setLocationStatus('error'),
      { timeout: 8000 }
    );
  };

  const selectedCategories = Object.entries(serviceMap)
    .filter(([, v]) => v.selected)
    .map(([catId, v]) => ({
      category_id: parseInt(catId, 10),
      price: parseFloat(v.price) || 0,
      price_type: v.price_type,
    }));

  // ---- STEP 1 -> STEP 2 ----
  const goToStep2 = () => {
    setError('');

    if (!form.name || !form.email || !form.phone || !form.password) {
      setError('Please fill in all required fields.');
      return;
    }
    if (form.password.length < 6) {
      setError('Password must be at least 6 characters.');
      return;
    }
    if (form.password !== form.confirmPassword) {
      setError('Passwords do not match.');
      return;
    }
    if (!/^[0-9]{10}$/.test(form.phone)) {
      setError('Enter a valid 10-digit phone number.');
      return;
    }

    setStep(2);
  };

  const goBackToStep1 = () => {
    setError('');
    setStep(1);
  };

  const handleSubmit = async () => {
    setError('');

    const cleanCustom = customServices.filter((c) => c.name.trim() !== '');

    if (selectedCategories.length === 0 && cleanCustom.length === 0) {
      setError('Select at least one service, or add your own.');
      return;
    }

    for (const c of selectedCategories) {
      if (!c.price || c.price <= 0) {
        const catName = categories.find((cat) => cat.id === c.category_id)?.name;
        setError(`Enter a valid price for "${catName}".`);
        return;
      }
    }
    for (const c of cleanCustom) {
      if (!c.price || c.price <= 0) {
        setError(`Enter a valid price for "${c.name}".`);
        return;
      }
    }

    const customPayload = cleanCustom.map((c) => ({
      custom_name: c.name.trim(),
      price: parseFloat(c.price) || 0,
      price_type: c.price_type,
    }));

    setLoading(true);
    try {
      const res = await fetch(`${API_URL}/api_sp_register.php`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
          name: form.name,
          email: form.email,
          phone: form.phone,
          password: form.password,
          bio: form.bio,
          latitude: location.latitude,
          longitude: location.longitude,
          categories: [...selectedCategories, ...customPayload],
        }),
      });
      const json = await res.json();

      if (json.error) {
        setError(json.error);
      } else if (json.status === 'ok' && json.sp_id) {
        const ownerData = {
          id: json.sp_id,
          name: json.sp_name,
          email: json.sp_email,
          token: json.token,
        };
        localStorage.setItem('owner', JSON.stringify(ownerData));
        router.push('/dashboard');
      } else {
        setError('Registration failed. Please try again.');
      }
    } catch (err) {
      setError('Server error. Please try again.');
    } finally {
      setLoading(false);
    }
  };

  return (
    <div className="min-h-screen bg-gray-950 flex items-center justify-center px-4 py-10">
      <div className="w-full max-w-md bg-gray-900 rounded-2xl shadow-2xl p-8 border border-gray-800">
        <div className="flex flex-col items-center mb-6">
          <div className="w-16 h-16 bg-green-600 rounded-full flex items-center justify-center text-3xl mb-3">
            🧰
          </div>
          <h1 className="text-2xl font-bold text-white">Service Provider</h1>
          <p className="text-gray-400 text-sm mt-1 text-center">
            Plumber, electrician, food stall, builder — whatever you offer, list it here.
          </p>
        </div>

        {/* Step indicator */}
        <div className="flex items-center gap-2 mb-6">
          <div className={`flex-1 h-1.5 rounded-full ${step >= 1 ? 'bg-green-600' : 'bg-gray-800'}`} />
          <div className={`flex-1 h-1.5 rounded-full ${step >= 2 ? 'bg-green-600' : 'bg-gray-800'}`} />
        </div>
        <p className="text-xs text-gray-500 text-center -mt-4 mb-6">
          Step {step} of 2 — {step === 1 ? 'Your details' : 'Your services'}
        </p>

        <div className="space-y-4">
          {step === 1 && (
            <>
              <input
                name="name"
                type="text"
                placeholder="Full Name"
                value={form.name}
                onChange={handleChange}
                className="w-full bg-gray-800 text-white rounded-xl px-4 py-3 text-sm outline-none focus:ring-2 focus:ring-green-500 placeholder-gray-500"
              />
              <input
                name="email"
                type="email"
                placeholder="Email Address"
                value={form.email}
                onChange={handleChange}
                className="w-full bg-gray-800 text-white rounded-xl px-4 py-3 text-sm outline-none focus:ring-2 focus:ring-green-500 placeholder-gray-500"
              />
              <input
                name="phone"
                type="tel"
                placeholder="Phone Number"
                value={form.phone}
                onChange={handleChange}
                maxLength={10}
                className="w-full bg-gray-800 text-white rounded-xl px-4 py-3 text-sm outline-none focus:ring-2 focus:ring-green-500 placeholder-gray-500"
              />
              <div className="grid grid-cols-2 gap-3">
                <input
                  name="password"
                  type="password"
                  placeholder="Password"
                  value={form.password}
                  onChange={handleChange}
                  className="w-full bg-gray-800 text-white rounded-xl px-4 py-3 text-sm outline-none focus:ring-2 focus:ring-green-500 placeholder-gray-500"
                />
                <input
                  name="confirmPassword"
                  type="password"
                  placeholder="Confirm Password"
                  value={form.confirmPassword}
                  onChange={handleChange}
                  className="w-full bg-gray-800 text-white rounded-xl px-4 py-3 text-sm outline-none focus:ring-2 focus:ring-green-500 placeholder-gray-500"
                />
              </div>
              <textarea
                name="bio"
                placeholder="About you (optional) — e.g. 10 years experience in electrical work"
                value={form.bio}
                onChange={handleChange}
                rows={2}
                className="w-full bg-gray-800 text-white rounded-xl px-4 py-3 text-sm outline-none focus:ring-2 focus:ring-green-500 placeholder-gray-500 resize-none"
              />

              {/* Location */}
              <div className="flex items-center justify-between bg-gray-800/50 rounded-xl px-4 py-3">
                <div>
                  <p className="text-sm text-gray-300">Your location</p>
                  <p className="text-xs text-gray-500">Helps match nearby customers</p>
                </div>
                <button
                  type="button"
                  onClick={detectLocation}
                  className="text-xs font-medium px-3 py-1.5 rounded-lg border border-gray-700 text-gray-300 hover:bg-gray-700"
                >
                  {locationStatus === 'loading'
                    ? 'Detecting...'
                    : locationStatus === 'done'
                    ? 'Detected ✓'
                    : 'Use my location'}
                </button>
              </div>

              {error && <p className="text-red-400 text-sm text-center">{error}</p>}

              <button
                onClick={goToStep2}
                className="w-full bg-green-600 hover:bg-green-700 text-white font-semibold rounded-xl py-3 text-sm transition"
              >
                Next: Choose your services →
              </button>

              <p className="text-center text-sm text-gray-500">
                Already have an account?{' '}
                <a href="/login" className="text-green-500 hover:underline">
                  Login
                </a>
              </p>
            </>
          )}

          {step === 2 && (
            <>
              {/* Services */}
              <div className="pt-2">
                <p className="text-sm text-gray-300 mb-1">What services do you offer?</p>
                <p className="text-xs text-gray-500 mb-3">Select all that apply and set your price.</p>

                {categoriesLoading ? (
                  <p className="text-sm text-gray-500">Loading categories...</p>
                ) : (
                  <div className="space-y-2">
                    {categories.map((cat) => {
                      const entry = serviceMap[cat.id];
                      const isSelected = !!entry?.selected;
                      return (
                        <div
                          key={cat.id}
                          className={`rounded-xl border px-3 py-2 transition-colors ${
                            isSelected ? 'border-green-600 bg-gray-800' : 'border-gray-800 bg-gray-800/40'
                          }`}
                        >
                          <label className="flex items-center gap-2 cursor-pointer">
                            <input
                              type="checkbox"
                              checked={isSelected}
                              onChange={() => toggleCategory(cat.id)}
                              className="h-4 w-4 rounded accent-green-600"
                            />
                            <span className="text-sm text-gray-200">{cat.name}</span>
                          </label>

                          {isSelected && (
                            <div className="mt-2 pl-6 flex items-center gap-2">
                              <input
                                type="number"
                                min="0"
                                value={entry.price}
                                onChange={(e) => updateServiceField(cat.id, 'price', e.target.value)}
                                placeholder="Price"
                                className="w-24 bg-gray-900 border border-gray-700 text-white rounded-lg px-2 py-1.5 text-sm outline-none focus:ring-2 focus:ring-green-500 placeholder-gray-600"
                              />
                              <select
                                value={entry.price_type}
                                onChange={(e) => updateServiceField(cat.id, 'price_type', e.target.value)}
                                className="bg-gray-900 border border-gray-700 text-white rounded-lg px-2 py-1.5 text-sm outline-none focus:ring-2 focus:ring-green-500"
                              >
                                <option value="fixed">Fixed price</option>
                                <option value="hourly">Per hour</option>
                              </select>
                            </div>
                          )}
                        </div>
                      );
                    })}

                    {/* Custom / "Other" services */}
                    {customServices.map((c) => (
                      <div
                        key={c.tempId}
                        className="rounded-xl border border-green-600 bg-gray-800 px-3 py-2"
                      >
                        <div className="flex items-center gap-2">
                          <input
                            type="text"
                            value={c.name}
                            onChange={(e) => updateCustomService(c.tempId, 'name', e.target.value)}
                            placeholder="Type your service (e.g. Tailor, AC Installation)"
                            className="flex-1 bg-gray-900 border border-gray-700 text-white rounded-lg px-2 py-1.5 text-sm outline-none focus:ring-2 focus:ring-green-500 placeholder-gray-600"
                          />
                          <button
                            type="button"
                            onClick={() => removeCustomService(c.tempId)}
                            className="text-gray-500 hover:text-red-400 text-sm px-2"
                          >
                            ✕
                          </button>
                        </div>
                        <div className="mt-2 flex items-center gap-2">
                          <input
                            type="number"
                            min="0"
                            value={c.price}
                            onChange={(e) => updateCustomService(c.tempId, 'price', e.target.value)}
                            placeholder="Price"
                            className="w-24 bg-gray-900 border border-gray-700 text-white rounded-lg px-2 py-1.5 text-sm outline-none focus:ring-2 focus:ring-green-500 placeholder-gray-600"
                          />
                          <select
                            value={c.price_type}
                            onChange={(e) => updateCustomService(c.tempId, 'price_type', e.target.value)}
                            className="bg-gray-900 border border-gray-700 text-white rounded-lg px-2 py-1.5 text-sm outline-none focus:ring-2 focus:ring-green-500"
                          >
                            <option value="fixed">Fixed price</option>
                            <option value="hourly">Per hour</option>
                          </select>
                        </div>
                      </div>
                    ))}

                    <button
                      type="button"
                      onClick={addCustomService}
                      className="w-full rounded-xl border border-dashed border-gray-700 text-gray-400 text-sm py-2 hover:border-green-600 hover:text-green-500 transition-colors"
                    >
                      + Other (type your own service)
                    </button>
                  </div>
                )}
              </div>

              {error && <p className="text-red-400 text-sm text-center">{error}</p>}

              <div className="flex gap-3">
                <button
                  type="button"
                  onClick={goBackToStep1}
                  className="w-1/3 bg-gray-800 hover:bg-gray-700 text-gray-300 font-semibold rounded-xl py-3 text-sm transition"
                >
                  ← Back
                </button>
                <button
                  onClick={handleSubmit}
                  disabled={loading}
                  className="flex-1 bg-green-600 hover:bg-green-700 disabled:opacity-50 text-white font-semibold rounded-xl py-3 text-sm transition"
                >
                  {loading ? 'Creating your account...' : 'Register as Provider'}
                </button>
              </div>
            </>
          )}
        </div>
      </div>
    </div>
  );
}