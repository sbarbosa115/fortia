import {contactSales} from '@console/entities/billing';
import {isEmail} from '@shared/lib';
import {useToast} from '@shared/ui';
import {useMutation} from '@tanstack/react-query';
import {type FormEvent, useState} from 'react';

export type ContactErrors = {email?: 'email'; phone?: 'phone'};

/** "Get in touch" about a plan (PRD §10.15): a valid email and a phone (1–50) are required. */
export function useContactSales(planId: string, defaultEmail: string) {
  const toast = useToast();
  const [email, setEmail] = useState(defaultEmail);
  const [phone, setPhone] = useState('');
  const [errors, setErrors] = useState<ContactErrors>({});
  const mutation = useMutation({
    mutationFn: contactSales,
    onError: (error) => toast.apiError(error),
  });

  const submit = (event: FormEvent) => {
    event.preventDefault();
    const found: ContactErrors = {};
    if (!isEmail(email)) {
      found.email = 'email';
    }
    const trimmed = phone.trim();
    if (trimmed.length < 1 || trimmed.length > 50) {
      found.phone = 'phone';
    }
    setErrors(found);
    if (Object.keys(found).length === 0) {
      mutation.mutate({
        type: 'plan',
        plan_id: planId,
        email: email.trim(),
        phone: trimmed,
      });
    }
  };

  return {
    email,
    setEmail,
    phone,
    setPhone,
    errors,
    submit,
    sending: mutation.isPending,
    sent: mutation.isSuccess,
  };
}
