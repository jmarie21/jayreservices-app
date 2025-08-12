<script setup lang="ts">
import { User } from '@/types/app-page-prop';
import { useForm } from '@inertiajs/vue3';
import { watch } from 'vue';
import { toast } from 'vue-sonner';
import { Button } from '../ui/button';
import { Dialog, DialogContent, DialogFooter, DialogHeader, DialogTitle } from '../ui/dialog';
import { Input } from '../ui/input';
import { Label } from '../ui/label';
import { Select, SelectContent, SelectGroup, SelectItem, SelectLabel, SelectTrigger, SelectValue } from '../ui/select';

const props = defineProps<{
    open: boolean;
    user?: User | null;
}>();

const emit = defineEmits<{
    (e: 'close'): void;
}>();

const formData = useForm({
    name: '',
    email: '',
    password: '',
    role: '',
});

// Watch for prop changes and set/reset form values
watch(
    () => props.user,
    (user) => {
        if (user) {
            formData.name = user.name ?? '';
            formData.email = user.email ?? '';
            formData.password = '';
            formData.role = user.role ?? '';
        } else {
            formData.reset();
        }
    },
    { immediate: true },
);

const handleSubmit = () => {
    const isEditing = !!props.user?.id;

    if (isEditing) {
        formData.put(route('user-mgmt.update', props.user!.id), {
            preserveState: true,
            onSuccess: () => {
                toast.success('Updated successfully!', {
                    description: 'User information updated successfully!',
                    position: 'top-right',
                });

                emit('close');
            },
        });
    } else {
        formData.post(route('user-mgmt.store'), {
            onSuccess: () => {
                toast.success('Added successfully!', {
                    description: 'User information added successfully!',
                    position: 'top-right',
                });
                emit('close');
            },
        });
    }
};
</script>

<template>
    <Dialog :open="open" @update:open="(v) => !v && emit('close')">
        <DialogContent class="sm:max-w-[425px]">
            <DialogHeader>
                <DialogTitle>{{ props.user ? 'Edit User' : 'Add New User' }}</DialogTitle>
            </DialogHeader>

            <form @submit.prevent="handleSubmit">
                <div class="grid gap-4 py-4">
                    <div class="grid grid-rows-2 items-center">
                        <Label>Name</Label>
                        <Input v-model="formData.name" placeholder="e.g Juan Dela Cruz" />
                        <span v-if="formData.errors.name" class="text-sm text-red-500">{{ formData.errors.name }}</span>
                    </div>

                    <div class="grid grid-rows-2 items-center">
                        <Label>Email</Label>
                        <Input v-model="formData.email" type="email" placeholder="example@email.com" />
                        <span v-if="formData.errors.email" class="text-sm text-red-500">{{ formData.errors.email }}</span>
                    </div>

                    <!-- Only show password for new user -->
                    <div v-if="!props.user" class="grid grid-rows-2 items-center">
                        <Label>Password</Label>
                        <Input v-model="formData.password" type="password" placeholder="Temporary password" />
                        <span v-if="formData.errors.password" class="text-sm text-red-500">{{ formData.errors.password }}</span>
                    </div>

                    <div class="grid grid-rows-2 items-center">
                        <Label>Role</Label>
                        <Select v-model="formData.role">
                            <SelectTrigger class="w-[180px]">
                                <SelectValue placeholder="Select a role" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectGroup>
                                    <SelectLabel>Roles</SelectLabel>
                                    <SelectItem value="admin">Admin</SelectItem>
                                    <SelectItem value="client">Client</SelectItem>
                                    <SelectItem value="editor">Editor</SelectItem>
                                </SelectGroup>
                            </SelectContent>
                        </Select>
                        <span v-if="formData.errors.role" class="text-sm text-red-500">{{ formData.errors.role }}</span>
                    </div>
                </div>

                <DialogFooter>
                    <Button type="submit">{{ props.user ? 'Save Changes' : 'Add User' }}</Button>
                </DialogFooter>
            </form>
        </DialogContent>
    </Dialog>
</template>
