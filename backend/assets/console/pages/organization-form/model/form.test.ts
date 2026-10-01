import {emptyMember, type Organization} from '@console/entities/organization';
import {ApiError} from '@shared/api';
import {describe, expect, it} from 'vitest';
import {
  addMember,
  appendMembers,
  changeMember,
  domainWarningCount,
  emptyForm,
  formFromOrganization,
  isValid,
  memberApiErrors,
  normalizeMemberRow,
  removeMember,
  toOrganizationPayload,
  validateForm,
} from './form';

const STORED: Organization = {
  organization_id: 'org-1',
  customer_id: 'ACME0001',
  name: 'Acme Retail',
  domain_email: 'acme.test',
  description: null,
  active: false,
  created_at: '2026-09-01T10:00:00Z',
  updated_at: '2026-09-01T10:00:00Z',
  organization_users: [
    {
      organization_user_id: 'm1',
      organization_id: 'org-1',
      name: 'ana',
      email: 'ana@acme.test',
      phone: null,
      role: 'Lead',
      area: null,
      created_at: null,
      updated_at: null,
    },
  ],
};

describe('organization form model (PRD §10.10)', () => {
  it('starts empty and active', () => {
    expect(emptyForm()).toEqual({
      name: '',
      domain: '',
      description: '',
      active: true,
      members: [],
    });
  });

  it('requires a name', () => {
    const errors = validateForm({...emptyForm(), name: '   '});

    expect(errors.name).toBe('nameRequired');
    expect(isValid(errors)).toBe(false);
    expect(isValid(validateForm({...emptyForm(), name: 'Acme'}))).toBe(true);
  });

  it('is invalid while a member row has a problem', () => {
    let form = addMember({...emptyForm(), name: 'Acme'});
    const key = form.members[0]!.key;

    expect(isValid(validateForm(form))).toBe(false);

    form = changeMember(form, key, {name: 'Ana', phone: '+57 300'});
    expect(isValid(validateForm(form))).toBe(true);
  });

  it('says a second row with the same email is already in the list', () => {
    const form = appendMembers({...emptyForm(), name: 'Acme'}, [
      {...emptyMember(), name: 'Ana', email: 'ana@acme.test'},
      {...emptyMember(), name: 'Ana B', email: ' ANA@acme.test'},
    ]);

    expect(validateForm(form).members[1]?.duplicate).toBe('duplicate');
  });

  it('normalizes a row when the user leaves it', () => {
    let form = addMember(emptyForm());
    const key = form.members[0]!.key;
    form = changeMember(form, key, {
      name: ' José  PÉREZ',
      email: ' Jose@Acme.TEST ',
      phone: '+57 (300) 1',
    });

    form = normalizeMemberRow(form, key);

    expect(form.members[0]).toMatchObject({
      name: 'jose perez',
      email: 'jose@acme.test',
      phone: '+573001',
    });
  });

  it('removes a row by its key', () => {
    let form = addMember(addMember(emptyForm()));
    const [first, second] = form.members;

    form = removeMember(form, first!.key);

    expect(form.members.map((m) => m.key)).toEqual([second!.key]);
  });

  it('counts the members whose email is on another domain (a warning only)', () => {
    const form = appendMembers({...emptyForm(), domain: ' Acme.test '}, [
      {...emptyMember(), name: 'A', email: 'a@acme.test'},
      {...emptyMember(), name: 'B', email: 'b@gmail.com'},
      {...emptyMember(), name: 'C', phone: '+1'},
    ]);

    expect(domainWarningCount(form)).toBe(1);
    expect(domainWarningCount({...form, domain: ''})).toBe(0);
    expect(isValid(validateForm({...form, name: 'Acme'}))).toBe(true);
  });

  it('sends the full member list, stored members with their id', () => {
    const form = addMember(formFromOrganization(STORED));
    const key = form.members[1]!.key;
    const payload = toOrganizationPayload(
      changeMember({...form, name: '  Acme   Retail ', description: ' '}, key, {
        name: 'Bruno',
        phone: '+57 300',
      }),
    );

    expect(payload).toEqual({
      name: 'Acme Retail',
      domain_email: 'acme.test',
      description: null,
      active: false,
      organization_users: [
        {
          organization_user_id: 'm1',
          name: 'ana',
          email: 'ana@acme.test',
          phone: null,
          role: 'Lead',
          area: null,
        },
        {
          name: 'bruno',
          email: null,
          phone: '+57300',
          role: null,
          area: null,
        },
      ],
    });
  });

  it('points API validation errors at the member row they are about', () => {
    const members = [
      {...emptyMember(), name: 'ana'},
      {...emptyMember(), name: 'bruno'},
    ];
    const error = new ApiError(400, 'VALIDATION_ERROR', 'x', {
      violations: [
        {field: 'name', message: 'Too long.'},
        {field: 'organization_users[1].email', message: 'Not valid.'},
        {field: 'organization_users[0][phone]', message: 'Too long.'},
      ],
    });

    expect(memberApiErrors(error, members)).toEqual([
      {position: 2, name: 'bruno', detail: 'Not valid.'},
      {position: 1, name: 'ana', detail: 'Too long.'},
    ]);
    expect(
      memberApiErrors(new ApiError(409, 'DOMAIN_EMAIL_CONFLICT', 'x'), members),
    ).toEqual([]);
  });
});
