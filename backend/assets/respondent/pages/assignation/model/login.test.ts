import {makeControl, makeQuestion} from '@respondent/entities/session';
import {describe, expect, it} from 'vitest';
import {canSignIn, loginBody, loginFields, loginKeyOf} from './login';

function control(name: string, type = 'text', required = false) {
  return makeControl({
    name,
    type: type as never,
    validations: required ? [{type: 'required'} as never] : [],
  });
}

describe('login field recognition (PRD §9.10 step 4)', () => {
  it('recognizes email and phone by their type, whatever the label', () => {
    expect(loginKeyOf(control('contacto', 'email'))).toBe('email');
    expect(loginKeyOf(control('x', 'tel'))).toBe('phone');
    expect(loginKeyOf(control('x', 'phone'))).toBe('phone');
  });

  it('recognizes each field by a keyword in the label, in English or Spanish, ignoring accents and case', () => {
    expect(loginKeyOf(control('Correo electrónico'))).toBe('email');
    expect(loginKeyOf(control('Your email'))).toBe('email');
    expect(loginKeyOf(control('Teléfono'))).toBe('phone');
    expect(loginKeyOf(control('Phone number'))).toBe('phone');
    expect(loginKeyOf(control('Cargo'))).toBe('role');
    expect(loginKeyOf(control('role'))).toBe('role');
    expect(loginKeyOf(control('Área'))).toBe('area');
    expect(loginKeyOf(control('area'))).toBe('area');
    expect(loginKeyOf(control('Nombre completo'))).toBe('name');
    expect(loginKeyOf(control('Full name'))).toBe('name');
  });

  it('does not recognize anything else', () => {
    expect(loginKeyOf(control('Favourite colour'))).toBeNull();
  });

  it('builds one field per recognized control, required by its validation, and leaves the rest out', () => {
    const slide = makeQuestion('registration-1', [
      control('name', 'text', true),
      control('email', 'email', true),
      control('phone', 'tel'),
      control('Favourite colour', 'text', true),
    ]);
    expect(loginFields(slide), 'unrecognized fields are not sent').toEqual([
      {key: 'name', control: 'name', required: true},
      {key: 'email', control: 'email', required: true},
      {key: 'phone', control: 'phone', required: false},
    ]);
  });

  it('enables the button when every required field is filled', () => {
    const fields = loginFields(
      makeQuestion('r', [
        control('name', 'text', true),
        control('email', 'email', true),
        control('area'),
      ]),
    );
    expect(canSignIn(fields, {name: 'Ana', email: ' '})).toBe(false);
    expect(canSignIn(fields, {name: 'Ana', email: 'ana@x.test'})).toBe(true);
  });
});

describe('the login is sent normalized (PRD §9.10 step 4)', () => {
  const fields = loginFields(
    makeQuestion('r', [
      control('name', 'text', true),
      control('email', 'email'),
      control('phone', 'tel'),
      control('role'),
      control('area'),
    ]),
  );

  it('sends the name lowercased, without accents and with collapsed spaces, the phone as digits with an optional +', () => {
    expect(
      loginBody(fields, {
        name: '  María   GÓMEZ ',
        email: ' Maria@Acme-Retail.TEST ',
        phone: '+57 (300) 111-2233',
        role: ' Store  manager ',
        area: 'Sales',
      }),
    ).toEqual({
      name: 'maria gomez',
      email: 'maria@acme-retail.test',
      phone: '+573001112233',
      role: 'Store manager',
      area: 'Sales',
    });
    expect(loginBody(fields, {name: 'x', phone: '300 111 2233'}).phone).toBe(
      '3001112233',
    );
  });

  it('leaves empty fields out', () => {
    expect(
      loginBody(fields, {name: 'Ana', email: 'ana@x.test', phone: ' '}),
    ).toEqual({name: 'ana', email: 'ana@x.test'});
  });

  it('sends the email as the name when the slide has no name field (the API requires one)', () => {
    const noName = loginFields(
      makeQuestion('r', [control('email', 'email', true)]),
    );
    expect(loginBody(noName, {email: 'ana@x.test'})).toEqual({
      name: 'ana@x.test',
      email: 'ana@x.test',
    });
  });
});
