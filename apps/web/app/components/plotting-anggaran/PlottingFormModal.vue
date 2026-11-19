<script setup lang="ts">
import type { ActiveJadwalPeriod, PlottingAnggaran, RefSumberDana } from '~/types/infrastruktur';
import type { BkkdProposal } from '~/types/bkkd';
import { useInfrastrukturApi } from '~/composables/useInfrastrukturApi';
import { useBkkdApi } from '~/composables/useBkkdApi';
import { usePermission } from '~/composables/usePermission';
import { useAuthStore } from '~/stores/auth';
import WilayahSelector from '~/components/common/WilayahSelector.vue';

interface Props {
  open: boolean;
  isEditing?: boolean;
  item?: PlottingAnggaran | null;
  defaultKecamatan?: number | null;
  defaultDesa?: number | null;
}

const props = withDefaults(defineProps<Props>(), {
  open: false,
  isEditing: false,
  item: null,
  defaultKecamatan: null,
  defaultDesa: null,
});

const emit = defineEmits<{
  (e: 'update:open', val: boolean): void;
  (e: 'success', message?: string): void;
  (e: 'created', item: PlottingAnggaran): void;
}>();

const auth = useAuthStore();
const { can, isAdmin } = usePermission();
const api = useInfrastrukturApi();
const bkkdApi = useBkkdApi();

const isOpen = computed({
  get: () => props.open,
  set: (val: boolean) => emit('update:open', val),
});

const isBappedaOrAdmin = computed(
  () =>
    isAdmin() ||
    auth.hasRole('admin') ||
    auth.hasRole('verifierBappeda') ||
    auth.hasRole('verifikator-bappeda') ||
    auth.hasRole('bappeda'),
);

const isDesaOnlyUser = computed(
  () => !isBappedaOrAdmin.value && Boolean(auth.user?.id_desa),
);

const canLookupBkkd = computed(
  () => !isDesaOnlyUser.value && can('bkkd-proposal-view'),
);

const submitting = ref(false);
const loadingSumberDana = ref(false);
const loadingProposals = ref(false);
const loadingJadwal = ref(false);
const sumberDanaList = ref<RefSumberDana[]>([]);
const bkkdProposals = ref<BkkdProposal[]>([]);
const jadwalConfigured = ref(false);
const jadwalBypass = ref(false);
const jadwalPeriods = ref<ActiveJadwalPeriod[]>([]);

const jenisBantuanOptions = computed(() => {
  if (isDesaOnlyUser.value) {
    return [
      { label: 'Dana Desa / APBDes', value: 'Dana Desa / APBDes' },
      { label: 'Alokasi Dana Desa (ADD)', value: 'ADD' },
      { label: 'Swadaya / PADes', value: 'PADes / Swadaya' },
    ];
  }
  return [
    { label: 'Bantuan Keuangan Khusus (BKK Desa)', value: 'BKK Desa' },
    { label: 'Dana Alokasi Khusus (DAK Fisik)', value: 'DAK Fisik' },
    { label: 'APBD Reguler Kabupaten', value: 'APBD Reguler' },
    { label: 'Bantuan Provinsi (Banprov)', value: 'Banprov' },
    { label: 'Dana Bagi Hasil (DBH)', value: 'DBH' },
    { label: 'Dana Desa / APBDes', value: 'Dana Desa / APBDes' },
    { label: 'Lainnya', value: 'Lainnya' },
  ];
});

const statusPenetapanOptions = [
  { label: 'Ditetapkan (Siap Digitasi)', value: 'ditetapkan' },
  { label: 'Draft (Belum Siap)', value: 'draft' },
  { label: 'Selesai', value: 'selesai' },
];

const formState = reactive({
  tahun_anggaran: new Date().getFullYear(),
  id_kecamatan: null as number | null,
  id_desa: null as number | null,
  jenis_bantuan: 'BKK Desa',
  nama_kegiatan: '',
  lokasi_kegiatan: '',
  sumber_dana: '',
  nomor_sk: '',
  tanggal_sk: '',
  status_penetapan: 'ditetapkan' as 'draft' | 'ditetapkan' | 'selesai',
  bkkd_proposal_id: null as string | null,
  target_pagu_anggaran: 0,
  target_panjang_m: 0,
});

const errors = reactive<Record<string, string>>({});
const formFeedback = ref<{ type: 'error'; message: string } | null>(null);

const filteredSumberDanaList = computed(() => {
  if (!isDesaOnlyUser.value) return sumberDanaList.value;
  return sumberDanaList.value.filter(
    (sd) =>
      String(sd.kategori || '').toLowerCase() === 'desa' ||
      ['DANA_DESA', 'DD', 'DDS', 'ADD', 'PADESA', 'SWADAYA'].includes(sd.kode.toUpperCase()),
  );
});

const sumberDanaSelectItems = computed(() =>
  filteredSumberDanaList.value.map((sd) => ({
    label: `${sd.nama} (${sd.kode})`,
    value: sd.kode,
  })),
);

const bkkdProposalItems = computed(() => [
  { label: 'Input Manual (Tidak Tautkan Proposal BKKD)', value: null },
  ...bkkdProposals.value.map((p) => ({
    label: `[${p.tahun_anggaran}] ${p.nomor_registrasi ? `${p.nomor_registrasi} - ` : ''}${p.judul_kegiatan}${p.desa?.nama_desa ? ` (Ds. ${p.desa.nama_desa})` : ''}`,
    value: p.id,
  })),
]);

async function loadSumberDana() {
  loadingSumberDana.value = true;
  try {
    const res = await api.fetchSumberDanaList({ active_only: true });
    sumberDanaList.value = res.data || [];

    const available = filteredSumberDanaList.value;
    if (
      available.length > 0 &&
      (!formState.sumber_dana || !available.some((sd) => sd.kode === formState.sumber_dana))
    ) {
      formState.sumber_dana = available[0]!.kode;
    }
  } catch (err) {
    console.error('Gagal memuat referensi sumber dana:', err);
  } finally {
    loadingSumberDana.value = false;
  }
}

async function loadBkkdProposals() {
  if (!canLookupBkkd.value) return;
  loadingProposals.value = true;
  try {
    const res = await bkkdApi.fetchProposals({ per_page: 200 });
    bkkdProposals.value = res?.data || [];
  } catch {
    bkkdProposals.value = [];
  } finally {
    loadingProposals.value = false;
  }
}

const openPlottingPeriods = computed(() =>
  jadwalPeriods.value.filter((p) => p.effective_open && p.allow_plotting),
);

const currentYearPeriod = computed(
  () =>
    jadwalPeriods.value.find(
      (p) => Number(p.tahun_anggaran) === Number(formState.tahun_anggaran),
    ) ?? null,
);

const isBlockedByJadwal = computed(() => {
  if (!jadwalConfigured.value || jadwalBypass.value) return false;
  if (!currentYearPeriod.value) return true;
  return !currentYearPeriod.value.effective_open || !currentYearPeriod.value.allow_plotting;
});

async function loadActiveJadwal() {
  loadingJadwal.value = true;
  try {
    const res = await api.fetchActiveJadwal({
      id_kecamatan: formState.id_kecamatan ?? undefined,
      id_desa: formState.id_desa ?? undefined,
    });
    jadwalConfigured.value = Boolean(res?.configured);
    jadwalBypass.value = Boolean(res?.is_bypass);
    jadwalPeriods.value = res?.data || [];

    if (
      !props.isEditing &&
      jadwalConfigured.value &&
      !jadwalBypass.value &&
      openPlottingPeriods.value.length > 0 &&
      !openPlottingPeriods.value.some((p) => Number(p.tahun_anggaran) === Number(formState.tahun_anggaran))
    ) {
      formState.tahun_anggaran = Number(openPlottingPeriods.value[0]!.tahun_anggaran);
    }
  } catch (err) {
    console.error('Gagal memuat jadwal tahun anggaran:', err);
  } finally {
    loadingJadwal.value = false;
  }
}

function handleProposalChange(proposalId: string | null) {
  formState.bkkd_proposal_id = proposalId;
  if (!proposalId) return;

  const proposal = bkkdProposals.value.find((p) => p.id === proposalId);
  if (!proposal) return;

  formState.tahun_anggaran = Number(proposal.tahun_anggaran) || formState.tahun_anggaran;
  formState.id_kecamatan = proposal.id_kecamatan ?? formState.id_kecamatan;
  formState.id_desa = proposal.id_desa ?? formState.id_desa;
  formState.nama_kegiatan = proposal.judul_kegiatan || formState.nama_kegiatan;
  formState.lokasi_kegiatan = proposal.lokasi_kegiatan || formState.lokasi_kegiatan;
  formState.jenis_bantuan = 'BKK Desa';

  const bkkSumber = sumberDanaList.value.find((sd) => sd.kode.toUpperCase() === 'BKK_DESA');
  if (bkkSumber) {
    formState.sumber_dana = bkkSumber.kode;
  }

  if (proposal.nilai_usulan && Number(proposal.nilai_usulan) > 0) {
    formState.target_pagu_anggaran = Number(proposal.nilai_usulan);
  }
  if (proposal.total_panjang_gis && Number(proposal.total_panjang_gis) > 0) {
    formState.target_panjang_m = Math.round(Number(proposal.total_panjang_gis) * 100) / 100;
  }
}

watch(
  () => props.open,
  (val) => {
    if (val) {
      formFeedback.value = null;
      Object.keys(errors).forEach((k) => delete errors[k]);
      if (props.isEditing && props.item) {
        Object.assign(formState, {
          tahun_anggaran: props.item.tahun_anggaran,
          id_kecamatan: props.item.id_kecamatan,
          id_desa: props.item.id_desa,
          jenis_bantuan: props.item.jenis_bantuan || (isDesaOnlyUser.value ? 'Dana Desa / APBDes' : 'BKK Desa'),
          nama_kegiatan: props.item.nama_kegiatan || '',
          lokasi_kegiatan: props.item.lokasi_kegiatan || '',
          sumber_dana: props.item.sumber_dana || '',
          nomor_sk: props.item.nomor_sk || '',
          tanggal_sk: props.item.tanggal_sk ? String(props.item.tanggal_sk).slice(0, 10) : '',
          status_penetapan: props.item.status_penetapan || 'ditetapkan',
          bkkd_proposal_id: props.item.bkkd_proposal_id || null,
          target_pagu_anggaran: Number(props.item.target_pagu_anggaran) || 0,
          target_panjang_m: Number(props.item.target_panjang_m) || 0,
        });
      } else {
        Object.assign(formState, {
          tahun_anggaran: new Date().getFullYear(),
          id_kecamatan: isDesaOnlyUser.value
            ? (auth.user?.id_kecamatan ?? null)
            : (props.defaultKecamatan ?? auth.user?.id_kecamatan ?? null),
          id_desa: isDesaOnlyUser.value
            ? (auth.user?.id_desa ?? null)
            : (props.defaultDesa ?? auth.user?.id_desa ?? null),
          jenis_bantuan: isDesaOnlyUser.value ? 'Dana Desa / APBDes' : 'BKK Desa',
          nama_kegiatan: '',
          lokasi_kegiatan: '',
          sumber_dana: filteredSumberDanaList.value[0]?.kode || '',
          nomor_sk: '',
          tanggal_sk: '',
          status_penetapan: 'ditetapkan',
          bkkd_proposal_id: null,
          target_pagu_anggaran: 0,
          target_panjang_m: 0,
        });
      }
      loadSumberDana();
      loadBkkdProposals();
      loadActiveJadwal();
    }
  },
  { immediate: true }
);

watch(
  [() => formState.id_kecamatan, () => formState.id_desa],
  () => {
    if (props.open) {
      loadActiveJadwal();
    }
  },
);

function formatRupiahPreview(val: number): string {
  return new Intl.NumberFormat('id-ID', {
    style: 'currency',
    currency: 'IDR',
    maximumFractionDigits: 0,
  }).format(val || 0);
}

async function handleSubmit() {
  formFeedback.value = null;
  Object.keys(errors).forEach((k) => delete errors[k]);

  if (!formState.nama_kegiatan.trim()) {
    errors.nama_kegiatan = 'Nama kegiatan wajib diisi.';
  }
  if (!formState.id_kecamatan) {
    errors.id_kecamatan = 'Kecamatan wajib dipilih.';
  }
  if (!formState.id_desa) {
    errors.id_desa = 'Desa wajib dipilih.';
  }
  if (!formState.sumber_dana) {
    errors.sumber_dana = 'Sumber dana wajib dipilih.';
  }
  if (formState.target_pagu_anggaran <= 0) {
    errors.target_pagu_anggaran = 'Pagu anggaran harus lebih dari 0.';
  }
  if (formState.target_panjang_m < 0) {
    errors.target_panjang_m = 'Target panjang tidak boleh negatif.';
  }
  if (isBlockedByJadwal.value) {
    errors.tahun_anggaran =
      currentYearPeriod.value?.reason ||
      `Tahun anggaran ${formState.tahun_anggaran} sedang ditutup untuk pengisian plotting pada wilayah ini.`;
  }

  if (Object.keys(errors).length > 0) {
    formFeedback.value = {
      type: 'error',
      message: 'Mohon periksa kolom yang ditandai merah.',
    };
    return;
  }

  submitting.value = true;
  try {
    const payload = {
      tahun_anggaran: Number(formState.tahun_anggaran),
      id_kecamatan: Number(formState.id_kecamatan),
      id_desa: Number(formState.id_desa),
      jenis_bantuan: formState.jenis_bantuan,
      nama_kegiatan: formState.nama_kegiatan.trim(),
      lokasi_kegiatan: formState.lokasi_kegiatan?.trim() || null,
      sumber_dana: formState.sumber_dana,
      nomor_sk: formState.nomor_sk?.trim() || null,
      tanggal_sk: formState.tanggal_sk || null,
      status_penetapan: formState.status_penetapan,
      bkkd_proposal_id: formState.bkkd_proposal_id || null,
      target_pagu_anggaran: Number(formState.target_pagu_anggaran),
      target_panjang_m:
        formState.target_panjang_m != null && Number(formState.target_panjang_m) > 0
          ? Number(formState.target_panjang_m)
          : 0,
    };

    let successMsg = '';
    if (props.isEditing && props.item?.id) {
      await api.updatePlotting(props.item.id, payload);
      successMsg = `Plotting '${payload.nama_kegiatan}' diperbarui.`;
    } else {
      const res = await api.createPlotting(payload);
      successMsg = `Plotting '${payload.nama_kegiatan}' ditambahkan.`;
      if (res?.data) {
        emit('created', res.data);
      }
    }

    isOpen.value = false;
    emit('success', successMsg);
  } catch (err: any) {
    formFeedback.value = {
      type: 'error',
      message: err?.data?.message || 'Gagal menyimpan plotting anggaran.',
    };
  } finally {
    submitting.value = false;
  }
}
</script>

<template>
  <UModal
    v-model:open="isOpen"
    :title="isEditing ? `Edit Plotting: ${item?.nama_kegiatan}` : 'Tambah Perencanaan Plotting Anggaran'"
    :description="isEditing ? 'Perbarui informasi pagu alokasi dan SK penetapan. Target panjang bersifat opsional.' : 'Daftarkan alokasi pagu kegiatan resmi sebagai dasar sebelum melakukan digitasi segmen.'"
    :ui="{ content: 'sm:max-w-2xl dark:bg-[#0b0f19] dark:border-white/[0.08]' }"
  >
    <template #body>
      <form class="space-y-4 py-1" @submit.prevent="handleSubmit">
        <div
          v-if="formFeedback"
          role="alert"
          class="p-2.5 rounded-lg bg-red-50/90 dark:bg-red-950/35 border border-red-200 dark:border-red-800/80 text-xs font-medium text-red-900 dark:text-red-100"
        >
          {{ formFeedback.message }}
        </div>

        <div
          v-if="jadwalConfigured"
          :class="[
            'p-2.5 rounded-lg border text-xs',
            jadwalBypass
              ? 'bg-sky-50/80 dark:bg-sky-950/30 border-sky-200 dark:border-sky-800/80 text-sky-900 dark:text-sky-100'
              : isBlockedByJadwal
                ? 'bg-red-50/85 dark:bg-red-950/35 border-red-200 dark:border-red-800/80 text-red-900 dark:text-red-100'
                : currentYearPeriod?.is_desa_override
                  ? 'bg-sky-50/85 dark:bg-sky-950/30 border-sky-200 dark:border-sky-800/80 text-sky-900 dark:text-sky-100'
                  : 'bg-emerald-50/80 dark:bg-emerald-950/30 border-emerald-200 dark:border-emerald-800/80 text-emerald-900 dark:text-emerald-100',
          ]"
        >
          <p v-if="jadwalBypass" class="font-medium">
            Mode Bappeda / Admin: akses penjadwalan di-bypass, Anda dapat menginput atau mengoreksi tahun anggaran kapan saja.
          </p>
          <p v-else-if="isBlockedByJadwal" class="font-medium">
            {{ currentYearPeriod?.reason || `Pengisian Tahun Anggaran ${formState.tahun_anggaran} sedang ditutup untuk wilayah terpilih.` }}
            <span v-if="openPlottingPeriods.length > 0" class="block mt-1 font-normal">
              Tahun dibuka: {{ openPlottingPeriods.map((p) => p.tahun_anggaran).join(', ') }}.
            </span>
          </p>
          <p v-else class="font-medium">
            Jadwal TA {{ formState.tahun_anggaran }} terbuka untuk pengisian plotting{{ currentYearPeriod?.is_desa_override ? ' (Dispensasi khusus Desa)' : '' }}.
          </p>
        </div>

        <div
          v-if="isDesaOnlyUser"
          class="p-2.5 rounded-lg bg-sky-50/80 dark:bg-sky-950/30 border border-sky-200 dark:border-sky-800/80 text-xs text-sky-900 dark:text-sky-100"
        >
          Role Desa dapat mendaftarkan kegiatan bersumber kategori Desa (Dana Desa / APBDes). Pagu SK BKKD dan APBD Kabupaten ditetapkan oleh Bappeda / Kecamatan.
        </div>

        <!-- Opsional: Tarik Data Proposal BKKD Resmi (untuk Bappeda/Kecamatan) -->
        <UFormField
          v-if="canLookupBkkd"
          label="Tautkan Proposal BKKD (Opsional)"
          help="Pilih proposal BKKD untuk mengisi otomatis kegiatan, wilayah, pagu, dan target panjang."
          size="sm"
        >
          <USelectMenu
            :model-value="formState.bkkd_proposal_id"
            :items="bkkdProposalItems"
            :loading="loadingProposals"
            value-key="value"
            label-key="label"
            placeholder="Pilih proposal BKKD atau isi manual..."
            class="w-full"
            @update:model-value="(val) => handleProposalChange((val as string | null) ?? null)"
          />
        </UFormField>

        <!-- Baris 1: Tahun Anggaran & Jenis Bantuan -->
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
          <UFormField label="Tahun Anggaran" required :error="errors.tahun_anggaran" size="sm">
            <UInput
              v-model.number="formState.tahun_anggaran"
              type="number"
              min="2020"
              max="2035"
              class="w-full font-mono"
              required
            />
          </UFormField>

          <UFormField label="Jenis Bantuan" required size="sm">
            <USelectMenu
              v-model="formState.jenis_bantuan"
              :items="jenisBantuanOptions"
              value-key="value"
              label-key="label"
              class="w-full"
            />
          </UFormField>
        </div>

        <!-- Baris 2: Wilayah Bertingkat (Kecamatan -> Desa) -->
        <div class="p-3 rounded-lg border border-gray-100 dark:border-white/[0.04] bg-gray-50/50 dark:bg-gray-900/30">
          <WilayahSelector
            v-model:modelKecamatan="formState.id_kecamatan"
            v-model:modelDesa="formState.id_desa"
            required
            layout="row"
          />
          <p v-if="errors.id_kecamatan || errors.id_desa" class="text-xs text-red-500 mt-1">
            {{ errors.id_kecamatan || errors.id_desa }}
          </p>
        </div>

        <!-- Baris 3: Nomor SK, Tanggal SK, Status Penetapan -->
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
          <UFormField
            :label="isDesaOnlyUser ? 'Nomor Perdes / APBDes' : 'Nomor SK / DPA'"
            size="sm"
          >
            <UInput
              v-model="formState.nomor_sk"
              :placeholder="isDesaOnlyUser ? 'Misal: PERDES/02/2026' : 'Misal: 188/105/KEP/2026'"
              class="w-full"
            />
          </UFormField>

          <UFormField label="Tanggal Dokumen" size="sm">
            <UInput
              v-model="formState.tanggal_sk"
              type="date"
              class="w-full"
            />
          </UFormField>

          <UFormField label="Status Penetapan" size="sm">
            <USelectMenu
              v-model="formState.status_penetapan"
              :items="statusPenetapanOptions"
              value-key="value"
              label-key="label"
              :search-input="false"
              :disabled="isDesaOnlyUser"
              class="w-full"
            />
          </UFormField>
        </div>

        <!-- Baris 4: Nama Kegiatan & Lokasi -->
        <UFormField label="Nama Paket / Kegiatan Pembangunan" required :error="errors.nama_kegiatan" size="sm">
          <UInput
            v-model="formState.nama_kegiatan"
            placeholder="Contoh: Peningkatan Jalan Poros Desa Ruas Dusun A - Dusun B"
            class="w-full"
            required
          />
        </UFormField>

        <UFormField label="Lokasi Pekerjaan Spesifik" size="sm" help="Keterangan tambahan lokasi / RT / Dusun setempat">
          <UInput
            v-model="formState.lokasi_kegiatan"
            placeholder="Contoh: Dusun Krajan RT 04 RW 02"
            class="w-full"
          />
        </UFormField>

        <!-- Baris 5: Sumber Dana, Target Pagu & Target Panjang -->
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
          <UFormField label="Sumber Pendanaan" required :error="errors.sumber_dana" size="sm">
            <USelectMenu
              v-model="formState.sumber_dana"
              :items="sumberDanaSelectItems"
              value-key="value"
              label-key="label"
              :loading="loadingSumberDana"
              class="w-full"
            />
          </UFormField>

          <UFormField label="Target Pagu Anggaran (Rp)" required :error="errors.target_pagu_anggaran" size="sm">
            <UInput
              v-model.number="formState.target_pagu_anggaran"
              type="number"
              min="0"
              step="1000000"
              class="w-full font-mono"
              required
            />
            <span class="text-[11px] font-semibold text-emerald-600 dark:text-emerald-400 font-mono mt-0.5 block truncate">
              {{ formatRupiahPreview(formState.target_pagu_anggaran) }}
            </span>
          </UFormField>

          <UFormField label="Target Panjang (m, Opsional)" :error="errors.target_panjang_m" size="sm">
            <UInput
              v-model.number="formState.target_panjang_m"
              type="number"
              min="0"
              step="10"
              placeholder="0 (opsional)"
              class="w-full font-mono"
            />
            <span class="text-[11px] text-gray-600 dark:text-gray-400 font-mono mt-0.5 block">
              <template v-if="Number(formState.target_panjang_m || 0) > 0">
                {{ Number(formState.target_panjang_m).toLocaleString('id-ID') }} meter
                <span v-if="formState.target_panjang_m >= 1000" class="text-blue-600 dark:text-blue-400">
                  ({{ (formState.target_panjang_m / 1000).toFixed(2) }} km)
                </span>
              </template>
              <template v-else>
                Opsional (diisi OPD / Desa / Kecamatan saat digitasi)
              </template>
            </span>
          </UFormField>
        </div>
      </form>
    </template>

    <template #footer>
      <div class="flex justify-end gap-2 w-full">
        <UButton
          label="Batal"
          color="neutral"
          variant="ghost"
          @click="isOpen = false"
        />
        <UButton
          :label="isEditing ? 'Simpan Perubahan' : 'Buat Plotting Kegiatan'"
          color="primary"
          :loading="submitting"
          :disabled="isBlockedByJadwal"
          @click="handleSubmit"
        />
      </div>
    </template>
  </UModal>
</template>
