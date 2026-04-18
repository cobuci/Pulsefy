<script setup lang="ts">
import { Form, Head, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import ProfileController from '@/actions/App/Http/Controllers/Settings/ProfileController';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { edit } from '@/routes/profile';

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Profile settings',
                href: edit(),
            },
        ],
    },
});

const page = usePage();
const user = computed(() => page.props.auth.user);
</script>

<template>
    <Head title="Profile settings" />

    <h1 class="sr-only">Profile settings</h1>

    <div class="flex flex-col space-y-6">
        <Heading
            variant="small"
            title="Profile information"
            description="Update your display name. Your Spotify email and avatar are managed by Spotify."
        />

        <div
            class="rounded-xl border border-border/60 bg-card/50 p-4 shadow-card"
        >
            <div class="flex items-center gap-4">
                <img
                    v-if="user.avatar"
                    :src="user.avatar"
                    :alt="user.name"
                    class="size-16 rounded-full object-cover ring-2 ring-accent/40"
                />
                <div class="flex flex-col gap-0.5">
                    <span class="text-sm font-medium text-foreground">{{
                        user.name
                    }}</span>
                    <span
                        v-if="user.email"
                        class="text-xs text-muted-foreground"
                        >{{ user.email }}</span
                    >
                </div>
            </div>
        </div>

        <Form
            v-bind="ProfileController.update.form()"
            class="space-y-6"
            v-slot="{ errors, processing }"
        >
            <div class="grid gap-2">
                <Label for="name">Display name</Label>
                <Input
                    id="name"
                    class="mt-1 block h-11 w-full rounded-xl"
                    name="name"
                    :default-value="user.name"
                    required
                    autocomplete="name"
                    placeholder="Full name"
                />
                <InputError class="mt-2" :message="errors.name" />
            </div>

            <div class="flex items-center gap-4">
                <Button
                    :disabled="processing"
                    data-test="update-profile-button"
                    class="bg-gradient-primary shadow-glow text-primary-foreground"
                    >Save</Button
                >
            </div>
        </Form>
    </div>
</template>
