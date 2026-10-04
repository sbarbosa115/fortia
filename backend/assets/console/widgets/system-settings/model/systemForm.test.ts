import {describe, expect, it} from 'vitest';
import {
  type SystemSettings,
  checkBody,
  smtpErrors,
  smtpFormFrom,
  smtpPatch,
} from './systemForm';

const SAVED: SystemSettings = {
  smtp_host: 'smtp.acme.test',
  smtp_port: 587,
  smtp_encryption: 'tls',
  smtp_username: 'mailer',
  smtp_password_set: true,
  smtp_from_email: 'hello@acme.test',
  smtp_from_name: 'Acme',
  openai_api_key_set: false,
  openai_api_key_last4: null,
};

const EMPTY: SystemSettings = {
  smtp_host: null,
  smtp_port: null,
  smtp_encryption: null,
  smtp_username: null,
  smtp_password_set: false,
  smtp_from_email: null,
  smtp_from_name: null,
  openai_api_key_set: false,
  openai_api_key_last4: null,
};

describe('the SMTP form', () => {
  it('starts from the saved server, never with the password', () => {
    expect(smtpFormFrom(SAVED)).toEqual({
      host: 'smtp.acme.test',
      port: '587',
      encryption: 'tls',
      username: 'mailer',
      password: '',
      fromEmail: 'hello@acme.test',
      fromName: 'Acme',
    });
  });

  it('suggests port 587 with STARTTLS for a new server', () => {
    const form = smtpFormFrom(EMPTY);
    expect(form.port).toBe('587');
    expect(form.encryption).toBe('tls');
  });

  it('needs a host, a port from 1 to 65535 and a sender email', () => {
    const form = smtpFormFrom(EMPTY);
    expect(smtpErrors(form)).toEqual({host: 'required', fromEmail: 'required'});
    expect(
      smtpErrors({
        ...form,
        host: 'smtp.x.test',
        port: '70000',
        fromEmail: 'nope',
      }),
    ).toEqual({port: 'port', fromEmail: 'email'});
    expect(
      smtpErrors({...form, host: 'smtp://x', fromEmail: 'a@b.test'}),
    ).toEqual({host: 'host'});
    expect(
      smtpErrors({...form, host: 'smtp.x.test', fromEmail: 'a@b.test'}),
    ).toEqual({});
  });

  it('sends the password only when one is typed, so the saved one is kept', () => {
    const form = smtpFormFrom(SAVED);
    expect(smtpPatch(form)).not.toHaveProperty('smtp_password');
    expect(smtpPatch({...form, password: 'new-pass'}).smtp_password).toBe(
      'new-pass',
    );
  });

  it('sends the server with a numeric port and empty optional fields as null', () => {
    const form = {...smtpFormFrom(SAVED), username: ' ', fromName: ''};
    expect(smtpPatch(form)).toEqual({
      smtp_host: 'smtp.acme.test',
      smtp_port: 587,
      smtp_encryption: 'tls',
      smtp_username: null,
      smtp_from_email: 'hello@acme.test',
      smtp_from_name: null,
    });
  });

  it('checks the form as it is, with the saved password when none is typed', () => {
    expect(checkBody(smtpFormFrom(SAVED))).not.toHaveProperty('smtp_password');
  });
});
