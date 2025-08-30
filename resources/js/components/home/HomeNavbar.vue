<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { Button } from '../ui/button';
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
                    <Button>
                        <Link href="/login">Login</Link>
                    </Button>

                    <!-- <Button variant="outline">
                        <Link href="/register">Signup</Link>
                    </Button> -->
                </div>
            </div>
        </nav>
    </div>
</template>
