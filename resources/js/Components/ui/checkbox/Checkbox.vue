<script setup>
import { CheckboxIndicator, CheckboxRoot, useForwardPropsEmits } from 'reka-ui';
import { Check } from 'lucide-vue-next';
import { cn } from '@/lib/utils';

const props = defineProps({
  checked: { type: [Boolean, String], default: undefined },
  defaultChecked: { type: Boolean, default: undefined },
  disabled: { type: Boolean, default: undefined },
  required: { type: Boolean, default: undefined },
  name: { type: String, default: undefined },
  value: { type: String, default: undefined },
  id: { type: String, default: undefined },
  asChild: { type: Boolean, default: false },
  as: { type: null, default: 'button' },
  class: { type: null, default: '' },
});

const emits = defineEmits(['update:checked']);
const forwarded = useForwardPropsEmits(props, emits);
</script>

<template>
  <CheckboxRoot
    v-bind="forwarded"
    :class="
      cn(
        'peer h-4 w-4 shrink-0 rounded-sm border border-primary ring-offset-background focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50 data-[state=checked]:bg-primary data-[state=checked]:text-primary-foreground transition-colors cursor-pointer',
        props.class,
      )
    "
  >
    <CheckboxIndicator class="flex h-full w-full items-center justify-center text-current">
      <slot>
        <Check class="h-3.5 w-3.5 stroke-[3]" />
      </slot>
    </CheckboxIndicator>
  </CheckboxRoot>
</template>
