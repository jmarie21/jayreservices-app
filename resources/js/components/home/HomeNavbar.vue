<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import { Button } from '../ui/button';

const page = usePage();

// Determine the dashboard route based on user role
const dashboardRoute = computed(() => {
    const user = page.props.auth.user;
    if (!user) return '/';

    return user.role === 'admin' ? '/admin-dashboard' : user.role === 'client' ? '/projects' : user.role === 'editor' ? '/editor-projects' : '/';
});
</script>

<template>
    <div>
        <nav className="fixed top-0 left-0 z-50 w-full bg-white shadow">
            <div className="mx-auto flex max-w-7xl items-center justify-between px-4 py-3">
                <!-- Logo -->
                <div class="flex items-center">
                    <!-- <img src="/jayreblack.png" alt="JayRE Logo" class="h-14 w-auto" /> -->
                    <span class="text-2xl font-bold">JayRE</span>
                </div>

                <!-- Buttons -->
                <div className="space-x-4">
                    <!-- Show Login if not logged in -->
                    <Button v-if="!page.props.auth.user">
                        <Link href="/login">Login</Link>
                    </Button>

                    <!-- Show Dashboard if logged in -->
                    <Button v-else>
                        <Link :href="dashboardRoute">Dashboard</Link>
                    </Button>

                    <!-- <Button variant="outline">
                        <Link href="/register">Signup</Link>
                    </Button> -->
                </div>
            </div>
        </nav>
    </div>
</template>
