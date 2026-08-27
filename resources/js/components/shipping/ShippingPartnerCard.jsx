import { useForm } from '@inertiajs/react';
import { useMemo, useState } from 'react';
import { toast } from 'sonner';

import { vietnamesePhoneError, normalizeVietnamesePhone } from '@/lib/vietnamesePhone';
import { useT } from '@/providers/I18nProvider';

const credentialAliases = {
    account: ['account', 'username', 'user_id', 'phone', 'user_mobile', 'partner_code', 'customer_code', 'client_id'],
    password: ['password'],
    token: ['token', 'api_token', 'secret_key'],
    shop_id: ['shop_id', 'pick_address_id', 'sender_profile_id', 'warehouse_id', 'account_id'],
    customer_code: ['customer_code', 'client_code', 'account_id'],
    api_key: ['api_key', 'token'],
    api_secret: ['api_secret', 'client_secret', 'secret_key'],
    contract_code: ['contract_code'],
    base_url: ['base_url'],
    provider_code: ['provider_code'],
    otp: ['otp'],
};

function findCredentialKey(provider, logicalKey) {
    const keys = provider.fields.map((field) => field.key);
    return (credentialAliases[logicalKey] ?? [logicalKey]).find((key) => keys.includes(key)) ?? logicalKey;
}

function firstError(errors, fallback) {
    const value = Object.values(errors || {})[0];
    if (Array.isArray(value)) return value[0];
    if (value && typeof value === 'object') return Object.values(value)[0] ?? fallback;
    return value ? String(value) : fallback;
}

function Field({ label, required = false, error = '', children, className = '' }) {
    return (
        <div className={`pssp-row${className ? ` ${className}` : ''}${error ? ' is-invalid' : ''}`}>
            <div className="pssp-control-col">
                <div className="pssp-control-wrap">{children}</div>
                {error ? <small className="pssp-field-error">{error}</small> : null}
            </div>
            <label className="pssp-label">
                {label ? (
                    <span className="h-label">
                        {label}
                        {required ? <span className="text-red"> (*)</span> : null}
                    </span>
                ) : null}
            </label>
        </div>
    );
}

function TextInput({ value, onChange, placeholder = '', disabled = false, type = 'text', required = false, invalid = false }) {
    return (
        <input
            type={type}
            value={value ?? ''}
            disabled={disabled}
            placeholder={placeholder}
            required={required}
            aria-invalid={invalid || undefined}
            onChange={(event) => onChange(event.target.value)}
            className={`form-control${invalid ? ' is-invalid' : ''}`}
            autoComplete="off"
        />
    );
}

function SelectInput({ value, onChange, children }) {
    return (
        <select className="form-control chosen" value={value ?? ''} onChange={(event) => onChange(event.target.value)}>
            {children}
        </select>
    );
}

function CheckboxInput({ checked, onChange, children }) {
    return (
        <label className="pssp-checkbox">
            <input type="checkbox" checked={Boolean(checked)} onChange={(event) => onChange(event.target.checked)} />
            <span>{children}</span>
        </label>
    );
}

function ActionButton({ children, onClick, type = 'button' }) {
    return <button type={type} className="btn btn-sm btn-primary pssp-inline-action" onClick={onClick}>{children}</button>;
}

function HelpBox({ provider }) {
    const t = useT();
    const lines = t(`shipping.partners_page.help.${provider.provider}`);
    const helpLines = Array.isArray(lines) ? lines : t('shipping.partners_page.help.default');

    return (
        <div className="pssp-help-row">
            <div className="pssp-control-col">
                <div className="notice pssp-help">
                    <b>{t('shipping.partners_page.help_title')}</b><br />
                    {(Array.isArray(helpLines) ? helpLines : []).map((line) => <span key={line}>- {line}<br /></span>)}
                    <br />
                    <b>{t('shipping.partners_page.disconnect_title')}</b><br />
                    <span>- {t('shipping.partners_page.disconnect_hint')}</span>
                </div>
            </div>
            <div className="pssp-label" aria-hidden="true" />
        </div>
    );
}

function fieldDisplayLabel(t, provider, field) {
    const byProvider = t(`shipping.providers.${provider.provider}.fields.${field.key}`);
    if (byProvider && !byProvider.startsWith('shipping.providers.')) {
        return byProvider;
    }
    return field.label ?? field.key;
}

function GenericCredentialFields({ provider, credential, setCredential, fieldErrors, secretSet }) {
    const t = useT();

    return provider.fields.map((field) => {
        const label = fieldDisplayLabel(t, provider, field);
        return (
            <Field key={field.key} label={label} required={field.required} error={fieldErrors[`credentials.${field.key}`] || fieldErrors[field.key] || ''}>
                <TextInput
                    type={field.is_secret ? 'password' : 'text'}
                    value={credential(field.key)}
                    onChange={(value) => setCredential(field.key, value)}
                    placeholder={field.is_secret && field.is_set ? field.masked : ''}
                    required={Boolean(field.required) && !(field.is_secret && secretSet.has(field.key))}
                    invalid={Boolean(fieldErrors[`credentials.${field.key}`] || fieldErrors[field.key])}
                />
            </Field>
        );
    });
}

function requiredCredentialKeys(provider) {
    return provider.fields.filter((field) => field.required).map((field) => field.key);
}

function providerDisplayName(t, provider) {
    const translated = t(`shipping.partners_page.names.${provider.provider}`);
    if (translated && !translated.startsWith('shipping.partners_page.names.')) {
        return translated;
    }
    return provider.label ?? provider.provider;
}

export function ShippingPartnerCard({ provider }) {
    const t = useT();
    const L = (key) => t(`shipping.partners_page.labels.${key}`);
    const O = (key) => t(`shipping.partners_page.options.${key}`);
    const P = (key) => t(`shipping.partners_page.placeholders.${key}`);

    const initialCredentials = Object.fromEntries(provider.fields.map((field) => [field.key, field.value ?? '']));
    const form = useForm({
        is_enabled: provider.is_enabled,
        integration_mode: provider.integration_mode ?? 'direct',
        webhook_secret: '',
        credentials: initialCredentials,
        settings: {
            pickup_mode: provider.settings?.pickup_mode ?? 'carrier_pickup',
            inspection_mode: provider.settings?.inspection_mode ?? 'view_only',
            goods_type: provider.settings?.goods_type ?? 'parcel',
            insurance_enabled: Boolean(provider.settings?.insurance_enabled),
            allow_partial_delivery: Boolean(provider.settings?.allow_partial_delivery),
            auto_create_waybill: Boolean(provider.settings?.auto_create_waybill),
            auto_restock_return: provider.settings?.auto_restock_return !== false,
            use_carrier_cod: provider.settings?.use_carrier_cod !== false,
            fixed_receiver_phone: provider.settings?.fixed_receiver_phone ?? '',
            sender_profile_id: provider.settings?.sender_profile_id ?? '',
            callback_url_enabled: Boolean(provider.settings?.callback_url_enabled),
            allow_insurance_order: Boolean(provider.settings?.allow_insurance_order),
            extra_services: provider.settings?.extra_services ?? [],
            extra_money: provider.settings?.extra_money ?? '',
            discount_code: provider.settings?.discount_code ?? '',
            pickup_time: provider.settings?.pickup_time ?? '',
            order_label: provider.settings?.order_label ?? '',
            failed_delivery_collect_fee: provider.settings?.failed_delivery_collect_fee ?? '',
            otp: provider.settings?.otp ?? '',
        },
    });
    const [clientErrors, setClientErrors] = useState({});

    const secretSet = useMemo(
        () => new Set(provider.fields.filter((field) => field.is_secret && field.is_set).map((field) => field.key)),
        [provider.fields],
    );

    const fieldErrors = { ...clientErrors, ...form.errors };

    const setCredential = (logicalKey, value) => {
        const key = findCredentialKey(provider, logicalKey);
        form.setData('credentials', { ...form.data.credentials, [key]: value });
        if (clientErrors[`credentials.${key}`] || clientErrors[key]) {
            setClientErrors((current) => {
                const next = { ...current };
                delete next[`credentials.${key}`];
                delete next[key];
                return next;
            });
        }
    };

    const credential = (logicalKey) => form.data.credentials[findCredentialKey(provider, logicalKey)] ?? '';
    const setSetting = (key, value) => form.setData('settings', { ...form.data.settings, [key]: value });
    const hasField = (logicalKey) => provider.fields.some((field) => (credentialAliases[logicalKey] ?? [logicalKey]).includes(field.key));
    const errorFor = (...keys) => keys.map((key) => fieldErrors[key] || fieldErrors[`credentials.${key}`]).find(Boolean) || '';

    const validateClient = () => {
        const next = {};
        requiredCredentialKeys(provider).forEach((key) => {
            const value = String(form.data.credentials?.[key] ?? '').trim();
            const field = provider.fields.find((item) => item.key === key);
            if (!value && !(field?.is_secret && secretSet.has(key))) {
                next[`credentials.${key}`] = t('shipping.partners_page.field_required', {
                    label: fieldDisplayLabel(t, provider, field ?? { key, label: key }),
                });
            }
        });

        if (['viettel_post'].includes(provider.provider) && !String(form.data.settings.sender_profile_id ?? '').trim()) {
            next['settings.sender_profile_id'] = t('shipping.partners_page.sender_required');
        }

        const fixedPhone = String(form.data.settings.fixed_receiver_phone ?? '').trim();
        if (fixedPhone) {
            const phoneError = vietnamesePhoneError(fixedPhone);
            if (phoneError) next['settings.fixed_receiver_phone'] = phoneError;
        }

        return next;
    };

    const submit = (event) => {
        event.preventDefault();
        const nextErrors = validateClient();
        if (Object.keys(nextErrors).length) {
            setClientErrors(nextErrors);
            toast.error(t('shipping.partners_page.check_required'));
            return;
        }

        setClientErrors({});
        const credentials = Object.fromEntries(
            Object.entries(form.data.credentials).filter(([key, value]) => !(secretSet.has(key) && value === '')),
        );
        const fixedPhone = String(form.data.settings.fixed_receiver_phone ?? '').trim();
        const settings = {
            ...form.data.settings,
            fixed_receiver_phone: fixedPhone ? (normalizeVietnamesePhone(fixedPhone) ?? fixedPhone) : '',
        };

        const name = providerDisplayName(t, provider);
        form.transform((data) => ({ ...data, credentials, settings })).put(`/admin/shipping-partners/${provider.provider}`, {
            preserveScroll: true,
            onSuccess: () => toast.success(t('shipping.partners_page.saved_config', { name })),
            onError: (errors) => toast.error(firstError(errors, t('shipping.partners_page.save_failed'))),
        });
    };

    const connectButton = (
        <ActionButton onClick={() => toast.message(t('shipping.partners_page.connect_sent'))}>
            <i className="fa fa-spinner" /> {t('shipping.partners_page.connect')}
        </ActionButton>
    );
    const verifyButton = (
        <ActionButton onClick={() => toast.message(t('shipping.partners_page.verify_sent'))}>
            <i className="fa fa-spinner" /> {t('shipping.partners_page.verify')}
        </ActionButton>
    );
    const providerName = providerDisplayName(t, provider);
    const useGeneric = !['vnpost', 'viettel_post', 'ghtk', 'ghn', 'jnt', 'holaship', 'spx', 'netship'].includes(provider.provider);
    const tokenField = provider.fields.find((field) => field.key === 'token');
    const baseUrlField = provider.fields.find((field) => field.key === 'base_url');

    return (
        <form className="pssp-form tab-pane active" onSubmit={submit} noValidate>
            <Field label="" className="pssp-provider-name-row">
                <span className="dvgh-name">{providerName}</span>
                <label className="pssp-status-toggle">
                    <input type="checkbox" checked={Boolean(form.data.is_enabled)} onChange={(event) => form.setData('is_enabled', event.target.checked)} />
                    <span>{t('shipping.partners_page.use_connection')}</span>
                </label>
            </Field>

            {provider.provider === 'netship' && (
                <>
                    <Field label={L('token_third_party')} required error={errorFor('token', 'credentials.token')}>
                        <TextInput
                            type="password"
                            required={!secretSet.has('token')}
                            value={credential('token')}
                            onChange={(value) => setCredential('token', value)}
                            placeholder={tokenField?.is_set ? (tokenField.masked ?? '') : P('paste_netship_token')}
                            invalid={Boolean(errorFor('token', 'credentials.token'))}
                        />
                    </Field>
                    <Field label={L('api_base_url')}>
                        <TextInput
                            value={credential('base_url')}
                            onChange={(value) => setCredential('base_url', value)}
                            placeholder={baseUrlField?.value || 'https://netship.vn'}
                        />
                    </Field>
                    <Field label={L('product_type')}>
                        <TextInput
                            value={credential('product_type')}
                            onChange={(value) => setCredential('product_type', value)}
                            placeholder={P('product_type_optional')}
                        />
                    </Field>
                    <Field label={L('delivery_note')}>
                        <SelectInput value={credential('delivery_note') || '1'} onChange={(value) => setCredential('delivery_note', value)}>
                            <option value="0">{O('none')}</option>
                            <option value="1">{O('view_only')}</option>
                            <option value="2">{O('open_and_try')}</option>
                        </SelectInput>
                    </Field>
                    <Field label={L('pickup_type')}>
                        <SelectInput value={credential('pickup_type') || '0'} onChange={(value) => setCredential('pickup_type', value)}>
                            <option value="0">{O('carrier_pickup')}</option>
                            <option value="1">{O('dropoff')}</option>
                        </SelectInput>
                    </Field>
                </>
            )}

            {provider.provider === 'vnpost' && (
                <>
                    <Field label={L('account')} required error={errorFor('account', findCredentialKey(provider, 'account'))}>
                        <TextInput required value={credential('account')} onChange={(value) => setCredential('account', value)} placeholder={P('vnpost_account')} invalid={Boolean(errorFor('account', findCredentialKey(provider, 'account')))} />
                    </Field>
                    <Field label={L('customer_code_vnpost')} required error={errorFor('customer_code', findCredentialKey(provider, 'customer_code'))}>
                        <TextInput required value={credential('customer_code')} onChange={(value) => setCredential('customer_code', value)} invalid={Boolean(errorFor('customer_code', findCredentialKey(provider, 'customer_code')))} />
                        {connectButton}
                    </Field>
                    <Field label={L('contract_code')}><TextInput value={credential('contract_code')} onChange={(value) => setCredential('contract_code', value)} placeholder={P('vnpost_contract')} /></Field>
                    <Field label={L('post_office')}><TextInput value={form.data.settings.sender_profile_id} onChange={(value) => setSetting('sender_profile_id', value)} placeholder={P('optional')} /></Field>
                    <Field label={L('inspection')}>
                        <SelectInput value={form.data.settings.inspection_mode} onChange={(value) => setSetting('inspection_mode', value)}><option value="view_only">{O('view_only')}</option><option value="none">{O('none')}</option><option value="open_and_try">{O('open_and_try')}</option></SelectInput>
                        <SelectInput value={form.data.settings.pickup_mode} onChange={(value) => setSetting('pickup_mode', value)}><option value="carrier_pickup">{O('collect_onsite')}</option><option value="dropoff">{O('dropoff_vnpost')}</option></SelectInput>
                    </Field>
                    <Field label={L('fixed_receiver_phone')}><TextInput value={form.data.settings.fixed_receiver_phone} onChange={(value) => setSetting('fixed_receiver_phone', value)} /></Field>
                </>
            )}

            {provider.provider === 'viettel_post' && (
                <>
                    <Field label={L('account')} required error={errorFor('account', findCredentialKey(provider, 'account'))}>
                        <TextInput required value={credential('account')} onChange={(value) => setCredential('account', value)} invalid={Boolean(errorFor('account', findCredentialKey(provider, 'account')))} />
                    </Field>
                    <Field label={L('password')} required error={errorFor('password', findCredentialKey(provider, 'password'))}>
                        <TextInput type="password" required={!secretSet.has(findCredentialKey(provider, 'password'))} value={credential('password')} onChange={(value) => setCredential('password', value)} placeholder={provider.fields.find((field) => field.key === findCredentialKey(provider, 'password'))?.masked ?? ''} invalid={Boolean(errorFor('password', findCredentialKey(provider, 'password')))} />
                        {connectButton}
                    </Field>
                    <Field label={L('vtp_token')}><TextInput value={credential('token')} onChange={(value) => setCredential('token', value)} placeholder={P('vtp_token_auto')} /></Field>
                    <Field label={L('sender_info')} required error={fieldErrors['settings.sender_profile_id'] || ''}>
                        <TextInput required value={form.data.settings.sender_profile_id} onChange={(value) => setSetting('sender_profile_id', value)} placeholder={P('sender_as_warehouse')} invalid={Boolean(fieldErrors['settings.sender_profile_id'])} />
                        <ActionButton onClick={() => toast.message(t('shipping.partners_page.loading_senders'))}>{t('shipping.partners_page.view_senders')}</ActionButton>
                    </Field>
                    <Field label={L('goods_type')}><SelectInput value={form.data.settings.goods_type} onChange={(value) => setSetting('goods_type', value)}><option value="parcel">{O('parcel')}</option><option value="document">{O('document')}</option><option value="fragile">{O('fragile')}</option></SelectInput></Field>
                    <Field label={L('extra_services')}><CheckboxInput checked={form.data.settings.insurance_enabled} onChange={(value) => setSetting('insurance_enabled', value)}>{L('use_insurance')}</CheckboxInput><CheckboxInput checked={form.data.settings.allow_partial_delivery} onChange={(value) => setSetting('allow_partial_delivery', value)}>{L('partial_delivery')}</CheckboxInput></Field>
                    <Field label={L('fixed_receiver_phone')}><TextInput value={form.data.settings.fixed_receiver_phone} onChange={(value) => setSetting('fixed_receiver_phone', value)} /></Field>
                </>
            )}

            {provider.provider === 'ghtk' && (
                <>
                    <Field label={L('account')} required error={errorFor('account', findCredentialKey(provider, 'account'))}>
                        <TextInput required value={credential('account')} onChange={(value) => setCredential('account', value)} invalid={Boolean(errorFor('account', findCredentialKey(provider, 'account')))} />
                    </Field>
                    <Field label={L('api_token')} required error={errorFor('token', findCredentialKey(provider, 'token'))}>
                        <TextInput type="password" required={!secretSet.has(findCredentialKey(provider, 'token'))} value={credential('token')} onChange={(value) => setCredential('token', value)} placeholder={provider.fields.find((field) => field.key === findCredentialKey(provider, 'token'))?.masked ?? ''} invalid={Boolean(errorFor('token', findCredentialKey(provider, 'token')))} />
                    </Field>
                    <Field label={L('ship_via')}><SelectInput value={form.data.settings.pickup_mode} onChange={(value) => setSetting('pickup_mode', value)}><option value="carrier_pickup">{O('ghtk_pickup')}</option><option value="dropoff">{O('shop_send')}</option></SelectInput></Field>
                    <Field label={L('inspection')}><SelectInput value={form.data.settings.inspection_mode} onChange={(value) => setSetting('inspection_mode', value)}><option value="view_only">{O('view_only')}</option><option value="none">{O('none')}</option></SelectInput></Field>
                    <Field label={L('fixed_receiver_phone')}><TextInput value={form.data.settings.fixed_receiver_phone} onChange={(value) => setSetting('fixed_receiver_phone', value)} /></Field>
                </>
            )}

            {provider.provider === 'ghn' && (
                <>
                    <Field label="Token" required error={errorFor('token', findCredentialKey(provider, 'token'))}>
                        <TextInput type="password" required={!secretSet.has(findCredentialKey(provider, 'token'))} value={credential('token')} onChange={(value) => setCredential('token', value)} placeholder={provider.fields.find((field) => field.key === findCredentialKey(provider, 'token'))?.masked ?? ''} invalid={Boolean(errorFor('token', findCredentialKey(provider, 'token')))} />
                        {connectButton}
                    </Field>
                    {hasField('shop_id') && (
                        <Field label={L('shop_id')} required error={errorFor('shop_id', findCredentialKey(provider, 'shop_id'))}>
                            <TextInput required value={credential('shop_id')} onChange={(value) => setCredential('shop_id', value)} invalid={Boolean(errorFor('shop_id', findCredentialKey(provider, 'shop_id')))} />
                        </Field>
                    )}
                    <Field label={L('default_shop')}><TextInput value={form.data.settings.sender_profile_id} onChange={(value) => setSetting('sender_profile_id', value)} placeholder={P('ghn_shop')} /></Field>
                    <Field label={L('service_package')}><SelectInput value={form.data.settings.goods_type} onChange={(value) => setSetting('goods_type', value)}><option value="parcel">{O('light')}</option><option value="fragile">{O('fragile')}</option><option value="document">{O('document')}</option></SelectInput></Field>
                    <Field label={L('inspection')}><SelectInput value={form.data.settings.inspection_mode} onChange={(value) => setSetting('inspection_mode', value)}><option value="view_only">{O('view_only')}</option><option value="none">{O('none')}</option></SelectInput></Field>
                </>
            )}

            {provider.provider === 'jnt' && (
                <>
                    <Field label={L('customer_code')} required error={errorFor('customer_code', findCredentialKey(provider, 'customer_code'))}>
                        <TextInput required value={credential('customer_code')} onChange={(value) => setCredential('customer_code', value)} invalid={Boolean(errorFor('customer_code', findCredentialKey(provider, 'customer_code')))} />
                    </Field>
                    <Field label={L('api_key')} required error={errorFor('api_key', findCredentialKey(provider, 'api_key'))}>
                        <TextInput type="password" required={!secretSet.has(findCredentialKey(provider, 'api_key'))} value={credential('api_key')} onChange={(value) => setCredential('api_key', value)} invalid={Boolean(errorFor('api_key', findCredentialKey(provider, 'api_key')))} />
                    </Field>
                    <Field label={L('api_secret')} required error={errorFor('api_secret', findCredentialKey(provider, 'api_secret'))}>
                        <TextInput type="password" required={!secretSet.has(findCredentialKey(provider, 'api_secret'))} value={credential('api_secret')} onChange={(value) => setCredential('api_secret', value)} invalid={Boolean(errorFor('api_secret', findCredentialKey(provider, 'api_secret')))} />
                        {connectButton}
                    </Field>
                    <Field label={L('inspection')}><SelectInput value={form.data.settings.inspection_mode} onChange={(value) => setSetting('inspection_mode', value)}><option value="view_only">{O('view_only')}</option><option value="none">{O('none')}</option></SelectInput></Field>
                </>
            )}

            {provider.provider === 'holaship' && (
                <>
                    <Field label={L('phone')} required error={errorFor('account', findCredentialKey(provider, 'account'))}>
                        <TextInput required value={credential('account')} onChange={(value) => setCredential('account', value)} invalid={Boolean(errorFor('account', findCredentialKey(provider, 'account')))} />
                    </Field>
                    <Field label={L('password')} required error={errorFor('password', findCredentialKey(provider, 'password'))}>
                        <TextInput type="password" required={!secretSet.has(findCredentialKey(provider, 'password'))} value={credential('password')} onChange={(value) => setCredential('password', value)} invalid={Boolean(errorFor('password', findCredentialKey(provider, 'password')))} />
                    </Field>
                    <Field label={L('otp')}><TextInput value={form.data.settings.otp} onChange={(value) => setSetting('otp', value)} />{verifyButton}</Field>
                    <Field label={L('shop_id')}><TextInput value={form.data.settings.sender_profile_id} onChange={(value) => setSetting('sender_profile_id', value)} /></Field>
                </>
            )}

            {provider.provider === 'spx' && (
                <>
                    <Field label={L('user_id')} required error={errorFor('account', findCredentialKey(provider, 'account'))}>
                        <TextInput required value={credential('account')} onChange={(value) => setCredential('account', value)} invalid={Boolean(errorFor('account', findCredentialKey(provider, 'account')))} />
                    </Field>
                    <Field label={L('secret_key')} required error={errorFor('token', findCredentialKey(provider, 'token'))}>
                        <TextInput type="password" required={!secretSet.has(findCredentialKey(provider, 'token'))} value={credential('token')} onChange={(value) => setCredential('token', value)} invalid={Boolean(errorFor('token', findCredentialKey(provider, 'token')))} />
                    </Field>
                    <Field label={L('account_id')} required error={errorFor('shop_id', findCredentialKey(provider, 'shop_id'))}>
                        <TextInput required value={credential('shop_id')} onChange={(value) => setCredential('shop_id', value)} invalid={Boolean(errorFor('shop_id', findCredentialKey(provider, 'shop_id')))} />
                        {connectButton}
                    </Field>
                    <Field label={L('use_insurance')}><CheckboxInput checked={form.data.settings.insurance_enabled} onChange={(value) => setSetting('insurance_enabled', value)}>{L('use_insurance')}</CheckboxInput></Field>
                    <Field label={L('pickup_method')}><SelectInput value={form.data.settings.pickup_mode} onChange={(value) => setSetting('pickup_mode', value)}><option value="carrier_pickup">{O('carrier_pickup_full')}</option><option value="dropoff">{O('dropoff_full')}</option></SelectInput></Field>
                    <Field label={L('inspection')}><SelectInput value={form.data.settings.inspection_mode} onChange={(value) => setSetting('inspection_mode', value)}><option value="view_only">{O('view_only')}</option><option value="none">{O('none')}</option></SelectInput></Field>
                    <Field label={L('failed_collect_fee')}><TextInput value={form.data.settings.failed_delivery_collect_fee} onChange={(value) => setSetting('failed_delivery_collect_fee', value)} /></Field>
                </>
            )}

            {useGeneric && (
                <>
                    <GenericCredentialFields provider={provider} credential={credential} setCredential={setCredential} fieldErrors={fieldErrors} secretSet={secretSet} />
                    <Field label={L('shop_warehouse')}><TextInput value={form.data.settings.sender_profile_id} onChange={(value) => setSetting('sender_profile_id', value)} /></Field>
                    <Field label={L('inspection')}><SelectInput value={form.data.settings.inspection_mode} onChange={(value) => setSetting('inspection_mode', value)}><option value="view_only">{O('view_only')}</option><option value="none">{O('none')}</option><option value="open_and_try">{O('open_and_try')}</option></SelectInput></Field>
                    <Field label={L('pickup_method')}><SelectInput value={form.data.settings.pickup_mode} onChange={(value) => setSetting('pickup_mode', value)}><option value="carrier_pickup">{O('carrier_pickup_full')}</option><option value="dropoff">{O('dropoff_full')}</option><option value="manual">{O('manual')}</option></SelectInput></Field>
                    <Field label={L('services')}><CheckboxInput checked={form.data.settings.insurance_enabled} onChange={(value) => setSetting('insurance_enabled', value)}>{L('use_insurance')}</CheckboxInput><CheckboxInput checked={form.data.settings.allow_partial_delivery} onChange={(value) => setSetting('allow_partial_delivery', value)}>{L('partial_delivery')}</CheckboxInput></Field>
                    <Field label={L('fixed_receiver_phone')}><TextInput value={form.data.settings.fixed_receiver_phone} onChange={(value) => setSetting('fixed_receiver_phone', value)} /></Field>
                </>
            )}

            <div className="pssp-save-row">
                <div className="pssp-control-col">
                    <button type="submit" disabled={form.processing} className="btn btn-sm btn-primary mr15">
                        <i className={`fa ${form.processing ? 'fa-spinner fa-spin' : 'fa-save'}`} /> {form.processing ? t('shipping.partners_page.saving') : t('shipping.partners_page.save')}
                    </button>
                    {provider.webhook_url && (
                        <span className="pssp-webhook">{t('shipping.partners_page.webhook_prefix')} {provider.webhook_url}</span>
                    )}
                </div>
                <div className="pssp-label" />
            </div>

            <HelpBox provider={provider} />
        </form>
    );
}
